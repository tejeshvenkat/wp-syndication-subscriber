# Contributing to WP Syndication Subscriber

Thank you for your interest in contributing. This guide covers local setup, running tests, and coding standards.

---

## Local Development Setup

### Requirements

- PHP 8.0+
- Composer
- [LocalWP](https://localwp.com/) or any local WordPress environment
- [WP Syndication Publisher](https://github.com/tejeshvenkat/wp-syndication-publisher) installed on a separate local site

### Step 1 — Clone the repo

```bash
git clone https://github.com/tejeshvenkat/wp-syndication-subscriber.git
cd wp-syndication-subscriber
```

### Step 2 — Install PHP dependencies

```bash
composer install
```

### Step 3 — Set up two local WordPress sites

Using LocalWP, create:
- `publisher.local` — install the publisher plugin here
- `subscriber.local` — install this plugin here

### Step 4 — Connect the sites

1. On `publisher.local` go to **Settings → Syndication**
2. Add `subscriber.local` as a subscriber
3. Copy the `secret_key` and `jwt_token`
4. On `subscriber.local` run:

```bash
curl -X POST https://subscriber.local/wp-json/wpss/v1/setup \
  -H "Content-Type: application/json" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -d '{"secret_key":"YOUR_KEY","jwt_token":"YOUR_TOKEN"}'
```

---

## Running Tests

```bash
# Run all tests
./vendor/bin/phpunit --testdox

# Run a specific test class
./vendor/bin/phpunit tests/php/Test_HMAC_Verifier.php --testdox
```

### Test environment setup

```bash
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

---

## Coding Standards

```bash
# Check standards
composer run lint

# Auto-fix where possible
./vendor/bin/phpcbf --standard=WordPress includes/
```

### Key rules

- All classes namespaced under `WPSyndication\Subscriber`
- All database queries use `$wpdb->prepare()`
- All user input sanitized on the way in
- All output escaped on the way out
- Public webhook endpoint authenticated by JWT + HMAC (not WP nonces — this is server-to-server)

---

## Commit Message Format

```
feat: add idempotency key validation to webhook receiver
fix: handle expired JWT tokens with clear 401 response
refactor: extract origin tracking to dedicated class
test: add HMAC verifier test for replay attack prevention
docs: update ARCHITECTURE.md with concurrent delivery notes
```

---

## Pull Request Process

1. Fork and create a feature branch
2. Write tests for new behaviour
3. Ensure `./vendor/bin/phpunit` passes
4. Ensure `composer run lint` has zero errors
5. Open PR with clear description of what and why

---

## Architecture

Read [ARCHITECTURE.md](./ARCHITECTURE.md) before making significant changes.
