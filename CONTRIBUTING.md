[a]: https://code.mensbeam.com/MensBeam/Policy/src/master/CODE-OF-CONDUCT.md
[b]: https://code.mensbeam.com/MensBeam/arsse/issues
[c]: http://www.php-fig.org/psr/psr-2/
[d]: https://getcomposer.org/
[e]: https://phpunit.de/manual/current/en/textui.html#textui.clioptions

# Contributing to The Arsse

We would love for you to contribute to our little project and to make The Arsse better. Here are the guidelines we would like you to follow:

* [Code of Conduct](#code-of-conduct)
* [Questions, Bugs, & Feature Requests](#questions-bugs-features)
* [Submission Guidelines](#submission-guidelines)
* [Coding Rules](#coding-rules)

## <a name="code-of-conduct"></a> Code of Conduct

We strive to keep The Arsse open and inclusive. Please read our [Code of Conduct][a] and follow its guidelines when interacting with others within this community.

## <a name="questions-issues-bugs-features"></a> Questions, Bugs & Feature Requests

Questions about how to use The Arsse, bugs, or feature requests can be directed at the [issues page][b].

## <a name="submission-guidelines"></a> Submission Guidelines

### Submitting an issue

Before you submit your issue search the archive to see if your question has been answered for you already. If your issue is new and hasn't been reported open a new issue; please do not report duplicate issues. We would like for you to provide the following information with each issue as it helps us help you:

1. *Overview* – Always submit stack traces if the bug produces one.
2. *Versions* – What version of The Arsse, php, and operating system are you using?
3. *Reproduce* - Provide a set of steps to reproduce the issue.
4. *Related* - Provide any issues you think might be related.
5. *Suggestions* - If you cannot fix the bug yourself provide any suggestions for fixing the issue.

### Submitting a Pull Request

Before you submit your pull request search for an existing open or closed pull request. Please refrain from duplicating pull requests.

## <a name="coding-rules"></a> Coding Rules

We would like to ensure consistency in our source code, so we follow the [PSR-2 guidelines][c] with one notable exception (utilizing the notation used and outlined in the original document):

**Opening braces for classes and methods MUST go on the same line, and closing braces MUST go on the next line after the body.**

## Running tests

To run the test suite, you must have [Composer][d] installed as well as the command-line PHP interpreter (this is normally required to use Composer). Port 8000 must also be available for use by the built-in PHP Web server.

``` sh
# first install dependencies
php composer.phar install
# run the tests
./robo test
# run the tests and produce a code coverage report in ./tests/coverage
./robo coverage
```

The example uses Unix syntax, but the test suite also runs in Windows. By default all tests are run; you can pass the same arguments to the test runner [as you would to PHPUnit][e]

``` sh
./robo test --testsuite "Configuration"
```
