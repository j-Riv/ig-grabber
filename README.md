# IG_GRABBER
> Instagram to WordPress Post. Created for my dog Henry.

## Setup
1. Log in or sign up to [Facebook Developers](https://developers.facebook.com).
2. Create a new app.
    - Add Instagram as a product.
    - Create a new Instagram app (<i>Products > Instagram > Basic Display</i>).
    - Add Instagram Tester(s)
    - Generate Access Token using the User Token Generator under Products > Instagram > Basic Display (<i>Tokens can only be generated for public accounts</i>).

Project assumes you have or will create a database table to save Instagram token data with the following columns: <i>access_token</i>, <i>token_type</i> and <i>expires_in</i>.

> Expects a config file:

The config file is used to connect to the wp database from outside WordPress to save Instagram access token data.

```php
return [
  'DB_NAME' => 'Database Name',
  'DB_USER' => 'Database Username',
  'DB_PASSWORD' => 'Database Password',
  'DB_HOST' => 'Database Hostname',
  'DB_TABLE' => 'Database Table'
];
```

## Usage
This can be setup with a cronjob to automatically download media from Instagram and dynamically create WordPress posts. I'm currently using this to power my dog's website [henrythepug.com](https://henrythepug.com)

