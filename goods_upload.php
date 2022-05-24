<?php
session_start(); 
?>
<!DOCTYPE html>
<html>
<head>
  <title>Загрузка исходного файла с товарами</title>
</head>
<body>
<h3>Загрузка исходного файла с товарами</h3>
<form action="g_upload.php" method="post" enctype="multipart/form-data">
	<input type="file" name="goodsfile">
	<input type="submit" value="Отправить">
</form>
</body>
</html>