# Enicore Maris

Enicore Maris is a collection of PHP utility classes. It provides essential tools for handling requests, managing 
sessions, interacting with databases, forms, etc.

## Installation

```shell
composer require enicore/maris
```

To copy from a local directory, add the following configuration in your composer.json:

```composer
    "require": {
        "enicore/maris": "@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "/path/to/enicore/maris",
            "options": {
                "symlink": true
            }
        }
    ]
```

## License

Enicore Maris is licensed under the MIT License. See LICENSE for more information.
