# API documentation

This directory contains executable API documentation in the
[JetBrains HTTP Client](https://www.jetbrains.com/help/phpstorm/http-client-in-product-code-editor.html)
format.

Select the `development` environment in PhpStorm and run a request using the
green gutter icon. Keep secrets in `http-client.private.env.json`; that file is
ignored by Git.

Every new or changed endpoint must be reflected in a `.http` file in this
directory.

