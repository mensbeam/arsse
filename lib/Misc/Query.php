<?php
/** @license MIT
 * Copyright 2017 J. King, Dustin Wilson et al.
 * See LICENSE and AUTHORS files for details */

declare(strict_types=1);
namespace JKingWeb\Arsse\Misc;

class Query {
    protected $qBody = ""; // main query body
    protected $tBody = []; // main query parameter types
    protected $vBody = []; // main query parameter values
    protected $qCTE = []; // Common table expression query components
    protected $tCTE = []; // Common table expression type bindings
    protected $vCTE = []; // Common table expression binding values
    protected $qWhere = []; // WHERE clause components
    protected $tWhere = []; // WHERE clause type bindings
    protected $vWhere = []; // WHERE clause binding values
    protected $qWhereNot = []; // WHERE NOT clause components
    protected $tWhereNot = []; // WHERE NOT clause type bindings
    protected $vWhereNot = []; // WHERE NOT clause binding values
    protected $group = []; // GROUP BY clause components
    protected $order = []; // ORDER BY clause components
    protected $limit = 0;
    protected $offset = 0;

    public function __construct(string $body = "", $types = null, $values = null) {
        $this->setBody($body, $types, $values);
    }

    public function setBody(string $body = "", $types = null, $values = null): self {
        $this->qBody = $body;
        if (!is_null($types)) {
            $this->tBody[] = $types;
            $this->vBody[] = $values;
        }
        return $this;
    }

    public function setCTE(string $tableSpec, string $body, $types = null, $values = null): self {
        $this->qCTE[] = "$tableSpec as ($body)";
        if (!is_null($types)) {
            $this->tCTE[] = $types;
            $this->vCTE[] = $values;
        }
        return $this;
    }

    public function setWhere(string $where, $types = null, $values = null): self {
        $this->qWhere[] = $where;
        if (!is_null($types)) {
            $this->tWhere[] = $types;
            $this->vWhere[] = $values;
        }
        return $this;
    }

    public function setWhereNot(string $where, $types = null, $values = null): self {
        $this->qWhereNot[] = $where;
        if (!is_null($types)) {
            $this->tWhereNot[] = $types;
            $this->vWhereNot[] = $values;
        }
        return $this;
    }

    public function setGroup(string ...$column): self {
        foreach ($column as $col) {
            $this->group[] = $col;
        }
        return $this;
    }

    public function setOrder(string ...$order): self {
        foreach ($order as $o) {
            $this->order[] = $o;
        }
        return $this;
    }

    public function setLimit(int $limit, int $offset = 0): self {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    public function pushCTE(string $tableSpec): self {
        // this function takes the query body and converts it to a common table expression, putting it at the bottom of the existing CTE stack
        // all WHERE, ORDER BY, and LIMIT parts belong to the new CTE and are removed from the main query
        $this->setCTE($tableSpec, $this->buildQueryBody(), [$this->tBody, $this->tWhere, $this->tWhereNot], [$this->vBody, $this->vWhere, $this->vWhereNot]);
        $this->tBody = [];
        $this->vBody = [];
        $this->qWhere = [];
        $this->tWhere = [];
        $this->vWhere = [];
        $this->qWhereNot = [];
        $this->tWhereNot = [];
        $this->vWhereNot = [];
        $this->order = [];
        $this->group = [];
        $this->setLimit(0, 0);
        return $this;
    }

    public function __toString(): string {
        $out = "";
        if (sizeof($this->qCTE)) {
            // start with common table expressions
            $out .= "WITH RECURSIVE ".implode(", ", $this->qCTE)." ";
        }
        // add the body
        $out .= $this->buildQueryBody();
        return $out;
    }

    public function getQuery(): string {
        return $this->__toString();
    }

    public function getTypes(): array {
        return ValueInfo::flatten([$this->tCTE, $this->tBody, $this->tWhere, $this->tWhereNot]);
    }

    public function getValues(): array {
        return ValueInfo::flatten([$this->vCTE, $this->vBody, $this->vWhere, $this->vWhereNot]);
    }

    protected function buildQueryBody(): string {
        $out = "";
        // add the body
        $out .= $this->qBody;
        // add any WHERE terms
        if (sizeof($this->qWhere) || sizeof($this->qWhereNot)) {
            $where = implode(" AND ", $this->qWhere);
            $whereNot = implode(" OR ", $this->qWhereNot);
            $whereNot = strlen($whereNot) ? "NOT ($whereNot)" : "";
            $where = implode(" AND ", array_filter([$where, $whereNot]));
            $out .= " WHERE $where";
        }
        // add any GROUP BY terms
        if (sizeof($this->group)) {
            $out .= " GROUP BY ".implode(", ", $this->group);
        }
        // add any ORDER BY terms
        if (sizeof($this->order)) {
            $out .= " ORDER BY ".implode(", ", $this->order);
        }
        // add LIMIT and OFFSET if either is specified
        if ($this->limit > 0 || $this->offset > 0) {
            $out .= " LIMIT ".($this->limit < 1 ? -1 : $this->limit);
            if ($this->offset > 0) {
                $out .= " OFFSET ".$this->offset;
            }
        }
        return $out;
    }
}
