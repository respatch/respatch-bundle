# RespatchBundle

**RespatchBundle** is a Symfony bundle that provides an API for the **Respatch** desktop application.

## What is Respatch?

Respatch is a native Linux application designed for developers and administrators who need a complete overview of what's happening in their Symfony Messenger. No more constantly refreshing web interfaces – Respatch brings you important information in real time, directly in your desktop environment.

[![Respatch Screenshot](docs/main-screen.png)](docs/main-screen.png)

For more information about the desktop application, visit [https://github.com/mostka-sk/respatch-gnome/](https://github.com/mostka-sk/respatch-gnome/).

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
$ composer require mostka-sk/respatch-bundle
```

> **Note:** If the routes are not automatically registered by Symfony Flex, you will need to create the `config/routes/respatch.yaml` file manually as described in **Step 3** below.

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require mostka-sk/respatch-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    MostkaSk\RespatchBundle\RespatchBundle::class => ['all' => true],
];
```

#### Step 3: Register Routes

Create the `config/routes/respatch.yaml` file to register the bundle's routes:

```yaml
respatch_api:
    resource: '@RespatchBundle/config/routes.php'
    prefix: /_respatch/api
```

## Configuration

For the desktop application to communicate with your Symfony server, you need to set a security token.

Add the `RESPATCH_TOKEN` environment variable to your `.env` or `.env.local` file:

```env
RESPATCH_TOKEN=some_secure_hash_token_123
```
