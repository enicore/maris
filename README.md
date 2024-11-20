# Enicore Maris

Enicore Maris is a collection of PHP utility classes. It provides essential tools for handling requests, managing 
sessions, interacting with databases, forms, etc.

## Installation

```shell
composer require enicore/maris
```

To copy from a local directory, add the following configuration in your composer.json:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "path/to/maris",
            "options": {
                "symlink": false
            }
        }
    ],
    "require": {
        "enicore/maris": "*"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

## License

Enicore Maris is licensed under the MIT License. See LICENSE for more information.
