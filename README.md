gitorade
========

Git management and automation for software development organizations

This is a framework that allows you to write automation for any git related activities. It has the following built in:

* Git command line API
* Github API
* Symfony2 framework
* ...more

Installation
============

Gitorade can be installed with [Composer](http://getcomposer.org)

To use the application:
```
php composer.phar install
```

To develop against the application:
```
php composer.phar install --dev
```

Please refer to the [Composer's documentation](https://github.com/composer/composer/blob/master/doc/00-intro.md#introduction)
for more installation and usage instructions.

Configuration
=====

Because Gitorade uses a local git repository and the Github API, you will need to configure it before you can use the functionality.

See:
```
Configuration/Type/*
```

You'll notice each configuration type has a method getConfigFilePath and getDefaultConfig

To create the config, create the file defined in getConfigFilePath for each config type, and populate with values specific to your
credentials / environment. The app/config directory has a .gitignore to ensure that your configs don't get committed if you're doing
development work.


Usage
=====

Run the command below to get a list of commands and what they do.

```
php app/gitorade.php
```
