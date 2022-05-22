<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ERROR);
set_time_limit(300); ini_set('max_execution_time', '300'); //300 seconds = 5 minutes

require 'vendor/autoload.php';
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;


if (!file_exists("config.ini")) {
	echo "Не обнаружен config.ini -- работа невозможна.";
	die;
}
$ini_array = parse_ini_file("config.ini", true);
getPrices();
sendtoftp();

function getPrices() {
	global $ini_array;
	$time1 = date("d-m-Y H:i:s");
	echo "Старт $time1<br> ";
	$exp_array = $ini_array["Exporter"];
	$xlsxname = $exp_array["tempfolder"] . "/" . $exp_array["xlsxpricesfilename"];

	$etm_array = $ini_array["ETM"]; 
	$session = getsession($etm_array);

	$in_array = $ini_array["In"]; 
	$sourcefile = $exp_array["tempfolder"] . "/" . $in_array["xlsxsourcefile"];

	if (!file_exists($sourcefile)) {
		die("<br>Не обнаружен исходный файл $sourcefile -- работа невозможна.");
	}
	$reader = ReaderEntityFactory::createXLSXReader();
	$reader = ReaderEntityFactory::createReaderFromFile($sourcefile);
	$reader->open($sourcefile);
	$articleCol = $ini_array["PricesFileColumns"]["articleCol"];
	$writer = WriterEntityFactory::createXLSXWriter();
	$writer->setTempFolder($exp_array["tempfolder"]);
	$writer->openToFile($xlsxname);
	$notfound = array();
	foreach ($reader->getSheetIterator() as $sheet) {
		foreach ($sheet->getRowIterator() as $rowindex=>$row) {
			if ($rowindex == 1) { $writer->addRow($row); continue;}			// первую строку просто копируем
			if ($row->getCellAtIndex($articleCol) !== null)					// если артикул - не пустой
				$article = $row->getCellAtIndex($articleCol)->getValue();
			else
				continue;													// просто пропускаем строку
			$myrow = array();
			for ($i = 0; $i < 12; $i++) {
				$cell = $row->getCellAtIndex($i);
				if ($cell !== null) 
					$myrow[] = $row->getCellAtIndex($i)->getValue();
				else
					$myrow[] = "";				
			}

			if (strpos($article, " ") === false) {							// если нет пробелов в артикуле
				$status = setGoodsProperties($session, $article, $myrow);
				if ($status == "200") {
					 setPriceProperties($session, $article, $myrow);
					 setQuantityProperties($session, $article, $myrow);
				} else {
					$notfound[] = $article;
				}
				$rowFromValues = WriterEntityFactory::createRowFromArray($myrow);
				// print_r($myrow);
				$writer->addRow($rowFromValues);
			} else {
				$writer->addRow($row); continue;							// если есть, то вставляем исходную строку
			}
			// if ($rowindex > 100) break;
		}
	}
	
	$reader->close();
	$writer->close();

	$time2 = date("d-m-Y H:i:s");
	echo "Финиш $time2<br> ";
	echo "Обработано $rowindex позиций<br>";
	$countnotfound = count($notfound);
	echo "Не найдено $countnotfound позиций:<br>";
	$show = implode(",", $notfound);
	echo $show;
	return 1;
}

function setQuantityProperties($session, $article, &$myrow) {
	global $ini_array;

	$res_array = makeRequest($article, $session, "/remains");	
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"]["InfoStores"];
		$fields = $ini_array["QuantityFieldsMapping"];
		foreach ($fields as $fieldnum => $field) {
			$colindex = intval(substr($fieldnum, 6));						// for "columnX" take only X
			if ($field == "StoreQuantRem") {		
				foreach ($arrData as $store) {
					switch ($store["StoreType"]) {
						case "rc" :
							$myrow[$colindex] = intval($store[$field]);
							break;
					}
				}
			}
		}
	} else {
		$myrow[9] = $res_array["status"]["code"] . " " . $res_array["status"]["message"];
	}
	return $res_array["status"]["code"] == "200";
}

function setPriceProperties($session, $article, &$myrow) {
	global $ini_array;

	$res_array = makeRequest($article, $session, "/price");	
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"]["rows"][0];
		$fields = $ini_array["PriceFieldsMapping"];
		foreach ($fields as $fieldnum => $field) {
			$colindex = intval(substr($fieldnum, 6));						// for "columnX" take only X
			$arrKeys = explode(",", $field);
			if (count($arrKeys) == 1) {				
				$myrow[$colindex] = $arrData[$field];
			}	
		}
	} else {
		$myrow[10] = $res_array["status"]["code"] . " " . $res_array["status"]["message"];
	}
	return $res_array["status"]["code"] == "200";
}

function setGoodsProperties($session, $article, &$row) {
	global $ini_array;

	$res_array = makeRequest($article, $session, "");
	if ($res_array === false) return false;
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"];
		$fields = $ini_array["GoodsFieldsMapping"];
		foreach ($fields as $fieldnum => $field) {
			$colindex = intval(substr($fieldnum, 6));		// for "columnX" take only X
			$arrKeys = explode(",", $field);
			if (count($arrKeys) == 1) {	
				$row[$colindex] = $arrData[$field];
			} else {
				if ($arrKeys[0] == "barcodes") {
					if (array_key_exists('barcodes',$arrData))
						$row[$colindex] = $arrData["barcodes"][0]["val"];
				}
				if ($arrKeys[0] == "color") {		
					$arrMeas = $arrData["gdsChars"];
					foreach ($arrMeas as $ch) {
						switch ($ch["gdsCharName"]) {
							case "Цвет корпуса" :
								$row[$colindex] = $ch["gdsCharVal"];
								break;
						}
					}
				}

			}
		}

	} else {
		$row[0] = $res_array["status"]["code"] . " " . $res_array["status"]["message"];
	}
	return $res_array["status"]["code"];
}


function makeRequest($article, $session, $querytype) {
	global $ini_array;

	$etm_array = $ini_array["ETM"]; 
	$source = $etm_array["source"];
	if (($res = file_get_contents($source .'goods/'.$article. $querytype . '?type=mnf&session-id='.$session)) === false) {
		$error = error_get_last();
		//echo "HTTP-запрос $source не сработал. Ошибка: " . $error['message'];
		$res_array = false;
	} else {
		$res_array = json_decode($res, true);
	}
	
	return $res_array;
}

function getsession($etm_array) {
	$res = file_get_contents($etm_array["source"] . 'user/login?log='.$etm_array["login"] . '&pwd='. $etm_array["password"]);
	$res_array = json_decode($res, true);
	$session = $res_array["data"]["session"];
	return $session;
}

function sendtoftp() {
	global $ini_array;
	// ftp settings
	$ftp_array = $ini_array["FTP"];
	$ftp_hostname = $ftp_array["destination"];
	$ftp_port = $ftp_array["port"];
	$ftp_username = $ftp_array["login"];
	$ftp_password = $ftp_array["password"];
	$remote_dir = $ftp_array["folder"];
	$exp_array = $ini_array["Exporter"];
	$src_file = $exp_array["tempfolder"] . "/" . $exp_array["xlsxpricesfilename"];
	$dest_file = $exp_array["xlsxpricesfilename"];

	//upload file
	if ($src_file!='')
	{
		// remote file path
		$dst_file = $remote_dir . $dest_file;
		
		// connect ftp
		$ftpcon = ftp_ssl_connect($ftp_hostname, $ftp_port) or die('<br>Error connecting to ftp server...');
		
		// ftp login
		$ftplogin = ftp_login($ftpcon, $ftp_username, $ftp_password) or die('<br>Error logging on to ftp server...');
		ftp_pasv($ftpcon, true);
		
		// ftp upload
		if (ftp_put($ftpcon, $dst_file, $src_file))
			echo 'File uploaded successfully to FTP server!';
		else
			echo 'Error uploading file! Please try again later.';
		
		// close ftp stream
		ftp_close($ftpcon);
	}	
}
?>