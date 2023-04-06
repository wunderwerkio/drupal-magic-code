# Magic Code

The Magic Code Drupal module integrates with the [Verification API](https://github.com/wunderwerkio/drupal-verification) and adds a `VerificationProvider` that handles verification via so-called **Magic Codes** (short, alphanumeric codes that the user may also type manually).

**Table of Contents:**

- [Motivation](#motivation)
- [How it works](#how-it-works)
  - [Code value](#code-value)
  - [The magic code entity](#the-magic-code-entity)
  - [Request Verification Data](#request-verification-data)
  - [Security Considerations](#security-considerations)
- [Usage](#usage)
  - [Generate a magic code](#generate-a-magic-code)
  - [Verify a magic code](#verify-a-magic-code)
  - [Use Magic Codes in E-Mail Templates](#use-magic-codes-in-e-mail-templates)
    - [Caveats](#caveats)

## Motivation

Using magic codes for verification (e.g. like Slack does it) is a popular and convenient way for users to verify a given operation or a passwordless login.

This module handles the creation, verification, and invalidation of those codes.

## How it works

A basic flow of how to use magic codes looks something like this:

1. Create a Magic Code for a given user and operation
2. Code is being sent to the user via E-Mail
3. User inputs this code somewhere (e.g. Verification form)
4. The code is then being validated by the Controller of the given operation
5. If the code is correct, it is invalidated and the operation is permitted to continue

### Code value

The code value consists of six uppercase alphanumeric characters that are separated by a dash in the middle.

E.g. `D3C-57X`

**The characters `0` and `O` are excluded to avoid user confusion.**

This length was chosen to find a sweet spot between convenience and security.

### The magic code entity

The magic code entity has the following important fields:

- `auth_user_id` The user who is eligible to use this magic code.
- `client` The consumer client that this magic code can be used with.
- `email` The email address the code was generated for. This may be different from the user's email address, when e.g. validating a new email address for the user.
- `operation` The operation this magic code verifies.
- `expired` When this magic code expires.
- `login_allowed` Whether this magic code can be used to log in the user before executing the `operation`.
- `status` Whether this magic code can be used or not.

In summary, a magic code can only be used to verify an operation if the following parameters are true:

- The code is being used by the same consumer client it was generated for.
- The request includes the same email address that this magic code was generated with.
- The target operation must match the operation this magic code can verify.
- The magic code must not be expired.
- The magic code's status must be `true`.
- For logins, the magic code must allow a login.

### Request Verification Data

The `MagicCode` verification provider expects a request to contain the verification data as an HTTP header:

`X-Verification-Magic-Code: ABC-123`

### Security Considerations

A magic code ultimately enables anyone who gets a hold of it to obtain an access token or even change the victims' password, etc.

To minimize the attack surface, the following precautions have been made:

- A magic code should be short-lived. The default TTL is 30 minutes.
- Using the Drupal core `flood` module, brute-forcing magic-codes is severely limited.
  The defaults are 50 attempts per IP-Address within an hour and 5 attempts per user within an hour.
- If a magic code allows a login for an operation, the login can only be made once.
- When a user entity gets updated, all magic codes for that user will be invalidated

## Usage

### Generate a magic code

The `magic_code.manager` service can be used to generate magic codes.

```php
<?php

$manager = \Drupal::service('magic_code.manager');

$operation = `my-operation`;
$user = User::load(1); // Get your desired user somehow.
$client = Consumer::load(1); // Get your desired consumer somehow.

// Code is the magic code entity.
$code = $manager->createNew($operation, $user, $client);

$codeValue = $code->getCodeValue();
```

### Verify a magic code

A magic code can also be manually verified (when not using the Verification API).

**If the magic code was generated with an email address that differs from the email address of the user, the email must be passed to the `verify` method as the last argument.**

```php
<?php

$manager = \Drupal::service('magic_code.manager');

$code = 'ABC-123';
$operation = `my-operation`;
$user = User::load(1); // Get your desired user somehow.
$client = Consumer::load(1); // Get your desired consumer somehow.

// Can be either
// - MagicCodeManagerInterface::VERIFY_MODE_OPERATION
// - MagicCodeManagerInterface::VERIFY_MODE_LOGIN
$mode = MagicCodeManagerInterface::VERIFY_MODE_OPERATION;

// @see \Drupal\magic_code\MagicCodeResult
$result = $manager->verify($code, $operation, $user, $client);
```

### Use Magic Codes in E-Mail Templates

Several tokens are provided to use magic codes in account emails.

**For security reasons, the tokens are only available in account emails!**

For each individual operation, a custom token must be used.

The module provides several built-in tokens to generate magic codes for the following operations:

|Token|Operation|
|-|-|
|`[user:magic-code-login]`|login|
|`[user:magic-code-register]`|register|
|`[user:magic-code-set-password]`|set-password|
|`[user:magic-code-cancel-account]`|cancel-account|

To make your custom operations available as tokens, you can use the `hook_magic_code_user_mail_token_operations_alter()` hook:

```php
<?php

/**
 * Implement hook_magic_code_user_mail_token_operations_alter().
 */
function my_module_magic_code_user_mail_token_operations_alter(&$operations) {
  $operations[] = 'added-operation';
}
```

#### Caveats

Please beware that for each token used in an email, a magic code is being generated.

E.g. when two tokens are used in the email template, two unique magic codes will be generated!
