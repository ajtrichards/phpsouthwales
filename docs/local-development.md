# Local Development

## Prerequisites

* [Docksal](https://docksal.io)
* [Docker](https://www.docker.com)
* [Git](https://git-scm.com)

## Initial installation

1. Clone the repository.

    ```
    git clone https://github.com/PHPSouthWales/php-south-wales-drupal.git php-south-wales

    cd php-south-wales
    ```

1. Start Docksal.

    ```
    fin start
    ```

1. Install the website.

    ```
    fin drupal-install
    ```

    If this stage fails due to `RuntimeException: Missing $settings['hash_salt'] in settings.php` - make sure you have the `.env` file in your root folder and there's a random string for the ENV variable `DRUPAL_SALT`

1. Open <http://phpsouthwales.docksal> in a browser.
