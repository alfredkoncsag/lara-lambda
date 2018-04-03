## Documentation

Lara-Lambda is a quick way to prepare a [Laravel](https://laravel.com), [Lumen](https://lumen.laravel.com) or [Laravel Zero](https://laravel-zero.com) project to run on [AWS Lambda](https://aws.amazon.com/lambda/).

### WIP

__This is a *Work In Progress* and is not ready to be used yet. Don't use it unless you know what you are doing. It will be ready for release soon.__

### Quick Start:

```

composer global require nsouto/lara-lambda


```

After the instalation you can create a new project by running the command:

```

lara-lambda new -a Laravel ProjectName


```

Supported Applications:

- Laravel
- Lumen
- LaravelZero

All Options:

 -  -a, --app[=APP]

 `PHP Application to create (Laravel|Lumen|LaravelZero) [default: "laravel"]`

 - -d, --directory[=DIRECTORY]

 `PHP Application directory to use inside ProjectName directory [default: "php/application"]`

 - -f, --force

 `Forces install even if the directory already exists`


## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

The Lumen framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

Laravel Zero is an open-source software licensed under the [MIT license](https://github.com/laravel-zero/laravel-zero/blob/stable/LICENSE.md).

Laravel Lambda is an open-source software licensed under the [MIT license](https://github.com/nsouto/laravel-lambda/blob/stable/LICENSE.md).

*Laravel is a trademark of Taylor Otwell.*