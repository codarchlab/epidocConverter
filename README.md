# epidocConverter

A php library to convert Epidoc-XMLs to HTML, using the XSLT Stylesheets and the Saxon XSLT 2.0 Processor or some fallback Stylesheets and the PHP XSLT 1.0 Processor.
 
Version 1.1, 2015. Author: Philipp Franck.

## Description
This is an abstract class, wich is used by both implementations. You can use it, if you want to select the best converter automatically.

## Tutorial

```php
try {
 $conv = epidocConverter::create($xmlData);
} catch (Exception $e) {
 echo $e->getMessage();
}
```

See `epidocConverterSaxon.class.php` and `epidocConverterFallback.class.php` for more hints.
