# F3-Willow
F3 Willow is adds some functionality to the F3 framework

### Features
- Abstract controller/resource class to add useful functionality to every controller. 
- Router API to programmatically build F3 route strings.

### Requirements
- PHP 8.x
- composer

### Installation
composer.json
```
"repositories": [
    {
        "type":"package",
        "package": {
            "name": "oreto/willow",
            "version":"master",
            "type": "library",
            "source": {
                "type": "git",
                "url": "https://github.com/ross-oreto/f3-willow.git",
                "reference":"master"
            }
        }
    }
]

"require: {
    "oreto/f3-willow": "dev-master" 
}
```

### The Willow Class
Initializing and running Willow is straightforward
```
Willow::equip($f3, [App::routes()])->run();
```
This call will likely be from index.php.
1. The first argument is an instance of fat-free Base class.
2. The second argument is an array of Routes which will be the result of a static method extended from the Willow class. In other words App extends Willow.

### The Router API
``` 
Routes::create(self::class)   1. The controller class name
           ->GET("home", "/") 2. The name of the route (home) and the uri pattern '/'
           ->handler('index') 3. The controller function name aka action
           ->build();         4. Build and return the Routes object.
```
It's also fluent:
```
return Routes::create(self::class)
    ->GET("list-items", "/")->handler('index')
    ->POST("save-item", "/")->handler('save')
    ->GET("get-item", "/@id")->handler('get')
    ->PUT("update-item", "/@id")->handler('update')
    ->DELETE("delete-item", "/@id")->handler('delete')
    ->build();
```

### logs
- By default log files are kept in /logs/app.log
- Configurable in config.ini
```
LOGS="../logs/"
logName=
```
Access the Willow logger statically or within the controller object using:
```
Willow::getLogger()->info($x);  1) global logger
$this->log->info($x);           2) protected controller logger 
```

### i18n
Lookup language message from src/dict/en.ini using
```
$message = Willow::dict('name');
```
In templates access dictionary using DICT preface:
```
{{ @DICT.404.message }}
```