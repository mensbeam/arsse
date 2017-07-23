<?php
declare(strict_types=1);
namespace JKingWeb\Arsse\Db;

abstract class AbstractDriver implements Driver {
    protected $locked = false;
    protected $transDepth = 0;
    protected $transStatus = [];

    public abstract function prepareArray(string $query, array $paramTypes): Statement;
    protected abstract function lock(): bool;
    protected abstract function unlock(bool $rollback = false) : bool;

    /** @codeCoverageIgnore */
    public function schemaVersion(): int {
        // FIXME: generic schemaVersion() will need to be covered for database engines other than SQLite
        try {
            return (int) $this->query("SELECT value from arsse_meta where key is schema_version")->getValue();
        } catch(Exception $e) {
            return 0;
        }
    }

    public function begin(bool $lock = false): Transaction {
        return new Transaction($this, $lock);
    }
    
    public function savepointCreate(bool $lock = false): int {
        if($lock && !$this->transDepth) {
            $this->lock();
            $this->locked = true;
        }
        $this->exec("SAVEPOINT arsse_".(++$this->transDepth));
        $this->transStatus[$this->transDepth] = self::TR_PEND;
        return $this->transDepth;
    }

    public function savepointRelease(int $index = null): bool {
        if(is_null($index)) {
            $index = $this->transDepth;
        }
        if(array_key_exists($index, $this->transStatus)) {
            switch($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $a = $index;
                    while(++$a && $a <= $this->transDepth) {
                        if($this->transStatus[$a] <= self::TR_PEND) {
                            $this->transStatus[$a] = self::TR_PEND_COMMIT;
                        }
                    }
                    $out = true;
                    break;
                case self::TR_PEND_COMMIT:
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $out = true;
                    break;
                case self::TR_PEND_ROLLBACK:
                    $this->transStatus[$index] = self::TR_COMMIT;
                    $out = false;
                    break;
                case self::TR_COMMIT:
                case self::TR_ROLLBACK: //@codeCoverageIgnore
                    throw new ExceptionSavepoint("stale", ['action' => "commit", 'index' => $index]);
                default:
                    throw new Exception("unknownSavepointStatus", $this->transStatus[$index]); //@codeCoverageIgnore
            }
            if($index==$this->transDepth) {
                while($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            if(!$this->transDepth && $this->locked) {
                $this->unlock();
                $this->locked = false;
            }
            return $out;
        } else {
            throw new ExceptionSavepoint("invalid", ['action' => "commit", 'index' => $index]);
        }
    }

    public function savepointUndo(int $index = null): bool {
        if(is_null($index)) {
            $index = $this->transDepth;
        }
        if(array_key_exists($index, $this->transStatus)) {
            switch($this->transStatus[$index]) {
                case self::TR_PEND:
                    $this->exec("ROLLBACK TRANSACTION TO SAVEPOINT arsse_".$index);
                    $this->exec("RELEASE SAVEPOINT arsse_".$index);
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $a = $index;
                    while(++$a && $a <= $this->transDepth) {
                        if($this->transStatus[$a] <= self::TR_PEND) {
                            $this->transStatus[$a] = self::TR_PEND_ROLLBACK;
                        }
                    }
                    $out = true;
                    break;
                case self::TR_PEND_COMMIT:
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $out = false;
                    break;
                case self::TR_PEND_ROLLBACK:
                    $this->transStatus[$index] = self::TR_ROLLBACK;
                    $out = true;
                    break;
                case self::TR_COMMIT:
                case self::TR_ROLLBACK: //@codeCoverageIgnore
                    throw new ExceptionSavepoint("stale", ['action' => "rollback", 'index' => $index]);
                default:
                    throw new Exception("unknownSavepointStatus", $this->transStatus[$index]); //@codeCoverageIgnore
            }
            if($index==$this->transDepth) {
                while($this->transDepth > 0 && $this->transStatus[$this->transDepth] > self::TR_PEND) {
                    array_pop($this->transStatus);
                    $this->transDepth--;
                }
            }
            if(!$this->transDepth && $this->locked) {
                $this->unlock(true);
                $this->locked = false;
            }
            return $out;
        } else {
            throw new ExceptionSavepoint("invalid", ['action' => "rollback", 'index' => $index]);
        }
    }

    public function prepare(string $query, ...$paramType): Statement {
        return $this->prepareArray($query, $paramType);
    }
}