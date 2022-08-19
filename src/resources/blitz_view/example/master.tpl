<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ if($title,$title,Lang::get('DefaultTitle')) }}</title>
</head>
<body>
<h1>Blitz View is working!</h1>
<p>This is master.tpl, may be use as extends some childs template like Blade, just place @yield, @extends, @section-@endsection Blade directives in HTML comment tag</p>
<div class="content">
    <!-- @yield('content') -->
</div>
</body>
</html>
