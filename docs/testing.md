# Testing (WIP)

To run all of the tests for the custom modules:

```bash
# Run all of the tests.
fin composer test

# Run only PHPUnit tests.
fin composer test-phpunit

# Run only PHPCS tests.
fin composer test-phpcs
```

Additonal arguments can be appended to the command. For example:

```
fin composer test-phpunit -- --filter get_the_event_name
```
