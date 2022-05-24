<?php
session_start(); 
?>
<!DOCTYPE html>
<html>
<head>
  <title>Загрузка исходного файла с ценами и остатками</title>
</head>
<body>
<h3>Загрузка исходного файла с ценами и остатками</h3>
<form action="p_upload.php" method="post" enctype="multipart/form-data">
	<input type="file" name="goodsfile">
	<input type="submit" value="Отправить">
</form>
</body>
</html>