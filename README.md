# Laravel Blitz View Package
Facade for Blitz template PHP extensions.

## Required
- Blitz extension (https://github.com/alexeyrybak/blitz)
  
- If you want use Redis for cache compiled templates may required https://github.com/phpredis/phpredis extension or https://github.com/predis/predis class
  Redis cache type use Laravel Redis Facade, configured in your redis config.

Default cache type is "file", prepared templates store into laravel "storage/blitz_compiled" folder (may change in config).
By default, caching is disabled, you may change "cache_enabled" to "true" in "config/blitz.php" 

## Installation For Laravel
Require this package with Composer
```bash
$ composer require nickyx3/blitz
```
Then run
```bash
$ php artisan vendor:publish --provider="NickyX3\Blitz\Providers\BlitzServiceProvider"
```

## Configuration
Default configuration
```php
    'templates_folder'      => 'blitz_view',
    'cache_type'            => 'file',
    'cache_enabled'         => false,
    'compiled_folder'       => 'blitz_compiled',
    'scope_lookup_limit'    => 8,
    'php_callbacks_first'   => 1,
    'namespace_finder'      => [
        'App\Helpers',
        'Illuminate\Support',
        'Illuminate\Support\Facades'
    ]
```

### Config parameters
- **templates_folder** - where source templates relative to laravel resources folder
- **cache_type** - 'file' or 'redis', default 'file'
- **cache_enabled** - enabled or disabled caching, default 'false'
- **compiled_folder** - where store cache relative to laravel storage folder, also if cache type 'redis' redis key 
  is full path to compiled file in filesystem like 'file' cache type
- **scope_lookup_limit** - blitz extension ini parameter
- **php_callbacks_first** - blitz extension ini parameter
- **namespace_finder** - in which namespace the template processor will look for classes specified in templates as callbacks.
  For example, you wrote in the template ```Lang::get('DefaultTitle')```. The processor will find the first matching class in these 
  namespaces and expand it into a fully qualified class name with a namespace (```Illuminate\Support\Facades\Lang::get('DefaultTitle')```). 
  If the class is not found, then the callback will be deleted.

## Usage
Example Controller
```php
use NickyX3\Blitz\Facade\BlitzView;

Route::get('/', function () {
    return BlitzView::apply('example.blitz-extend',['title'=>'Blitz Title']);
});
```
Method ```apply``` returns ```Illuminate\Http\Response```, also method ```make``` is alias for ```apply```

## Command
The command is also available to clear the template cache
```bash
$ php artisan blitz:clear
```

### Exceptions
If Blitz generate error, throw custom BlitzException with integrated renderer.
This exception will be rendered if your env ```APP_DEBUG=true```, otherwise simple laravel error 500 with abort helper.

## Template Syntax Features
Unlike Blitz, which can only do include, template "up" inheritance works like in Blade Engine. 
The following Blade directives are supported: ```@yield```, ```@extends```, ```@section``` and ```@endsection``` placed in an HTML comment tag

### Examples
- "example/master.tpl" template
```html
<!DOCTYPE html>
<html lang="en">
<body>
<!-- @yield('content') -->
</body>
</html>
``` 
- "blitz-extend.tpl" template
```html
<!-- @extends('example.master') -->
<!-- @section('content') -->
<div class="child-template">this is template extends example/master.tpl</div>
<!-- @endsection -->
```

## Have fun!

Perhaps the code is not very good, I'm new to Laravel and also very poorly documented because I'm going on vacation. Maybe I'll make detailed comments later :-)

Additions and corrections welcome
