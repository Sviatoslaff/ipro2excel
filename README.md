# ipro2excel
 Makes XLSX files for prices and remains from ETM API, using box/scout.

Импортирование информации по продукции из системы iPro ETM (API) и сохранение в Excel файлы. 
Описание ETM API - в каталоге docs.

В проекте используются внешние компоненты:
	1. Box/Spout - работа с Excel таблицами.

Порядок установки.

1. Загрузить архив на хостинг в public_html
2. Распаковать архив. Будет создана папка ipro2excel-main.
3. В папке находится файл config.ini. Он содержит настройки доступа к системам. Рекомендуется заменить пароли/логины после установки.
4. Нужно дать права пользователя и группы от крон-задания на папку tmp в каталоге etm2excel.
5. Для возможности загрузки исходных файлов нужно дать права на запись на папку source пользователю www. Файлом .htaccess нужно выставить разрешение доступа с определенного адреса IP.

Порядок работы.

1. Получение файла с товарами (весо-габаритные характеристики).
- Выложить в папку ipro2excel файл с артикулами, назвать article.txt. Каждый артикул идет с новой строки. 
- Запустить http://вашсайт.ру/ipro2excel/getgoodsfile.php 
- Результат сохранится в виде файла exportgoods.xlsx (название меняется в config.ini, ключ xlsxgoodsfilename). 
- Скачать файл можно по url http://вашсайт.ру/ipro2excel/tmp/exportgoods.xlsx

2. Получение файла с ценами и остатками.
- Выложить в папку ipro2excel/tmp исходный файл с артикулами, имя файла указано в конфигурационном файле, ключ 
- Запустить http://вашсайт.ру/ipro2excel/getgoodsfile.php 
- Результат сохранится в виде файла exportprices.xlsx (название меняется в config.ini, ключ xlsxpricesfilename) на FTP-сервер, указанный в конфигурационном файле.
- Скачать файл можно по url http://вашсайт.ру/ipro2excel/tmp/exportprices.xlsx

Как загрузить исходные файлы?
1. Для загрузки файла с товарами - использовать goods_upload.php
2. Для загрузки файла с ценами - использовать prices_upload.php


Дополнительная информация по файлу с ценами.
1. При необходимости файл исходный Excel можно расширить дополнительными полями, описав поля в конфигурационном файле, секция PricesFileColumns
2. Характеристики продукта из первого уровня можно получить без программирования, описав их в секции GoodsFieldsMapping.

* Конец документа.
