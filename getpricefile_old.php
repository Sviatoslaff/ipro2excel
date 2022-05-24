<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ERROR);
set_time_limit(300); ini_set('max_execution_time', '300'); //300 seconds = 5 minutes

require 'vendor/autoload.php';
// require_once 'vendor/box/spout/src/Spout/Autoloader/autoload.php';
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

function getPrices() {
	global $ini_array;
	$time1 = date("d-m-Y H:i:s");
	echo "Старт $time1<br> ";
	$exp_array = $ini_array["Exporter"];
	$xlsxname = $exp_array["tempfolder"] . "/" . $exp_array["xlsxpricesfilename"];

	$etm_array = $ini_array["ETM"]; 
	$session = getsession($etm_array);

	$in_array = $ini_array["In"]; 
	$sourcefile = $exp_array["sourcefolder"] . "/" . $in_array["xlsxsourcefile"];

	if (!file_exists($sourcefile)) {
		die("<br>Не обнаружен исходный файл $sourcefile -- работа невозможна.");
	}
	$reader = ReaderEntityFactory::createXLSXReader();
	$reader = ReaderEntityFactory::createReaderFromFile($sourcefile);
	// $reader->setShouldPreserveEmptyRows(true);
	$reader->open($sourcefile);
	$articleCol = $ini_array["PricesFileColumns"]["articleCol"];
	$writer = WriterEntityFactory::createXLSXWriter();
	$writer->setTempFolder($exp_array["tempfolder"]);
	$writer->openToFile($xlsxname);

	$notfound = array();
	foreach ($reader->getSheetIterator() as $sheet) {
		foreach ($sheet->getRowIterator() as $rowindex=>$row) {
			if ($rowindex == 1) { $writer->addRow($row); continue;}
			$article = $row->getCellAtIndex($articleCol)->getValue();
			for ($i = 0; $i < 20; $i++) {
				$myrow[] = $row->getCellAtIndex($i)->getValue();
			}
			if (strpos($article, " ") === false) {	// если нет пробелов
				$status = setGoodsProperties($session, $article, $row);
				if ($status == "200") {
					setPriceProperties($session, $article, $row);
					setQuantityProperties($session, $article, $row);
				} else {
					$notfound[] = $article;
				}
				$writer->addRow($row);
			} else {
				$writer->addRow($row); continue;
			}
			if ($rowindex > 100) break;
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

function setQuantityProperties($session, $article, $row) {
	global $ini_array;

	$res_array = makeRequest($article, $session, "/remains");	
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"]["InfoStores"];
		$fields = $ini_array["QuantityFieldsMapping"];
		foreach ($fields as $fieldnum => $field) {
			$colindex = intval(substr($fieldnum, 6));		// for "columnX" take only X
			if ($field == "StoreQuantRem") {		
				foreach ($arrData as $store) {
					switch ($store["StoreType"]) {
						case "rc" :
							$row->setCellAtIndex(WriterEntityFactory::createCell(intval($store[$field])), $colindex);
							break;
					}
				}
			}
		}
	} else {
		$row->setCellAtIndex(WriterEntityFactory::createCell($res_array["status"]["code"] . " " . $res_array["status"]["message"]), 9);
	}
	return $res_array["status"]["code"] == "200";
}

function setPriceProperties($session, $article, $row) {
	global $ini_array;

	$res_array = makeRequest($article, $session, "/price");	
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"]["rows"][0];
		$fields = $ini_array["PriceFieldsMapping"];
		foreach ($fields as $fieldnum => $field) {
			$colindex = intval(substr($fieldnum, 6));		// for "columnX" take only X
			$arrKeys = explode(",", $field);
			if (count($arrKeys) == 1) {				
				$row->setCellAtIndex(WriterEntityFactory::createCell($arrData[$field]), $colindex);	
			}	
		}
	} else {
		$row->setCellAtIndex(WriterEntityFactory::createCell($res_array["status"]["code"] . " " . $res_array["status"]["message"]), 10);
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
				$row->setCellAtIndex(WriterEntityFactory::createCell($arrData[$field]), $colindex);	
			} else {
				if ($arrKeys[0] == "barcodes") {
					if (array_key_exists('barcodes',$arrData))
						$row->setCellAtIndex(WriterEntityFactory::createCell($arrData["barcodes"][0]["val"]), $colindex);	
				}
				if ($arrKeys[0] == "color") {		
					$arrMeas = $arrData["gdsChars"];
					foreach ($arrMeas as $ch) {
						switch ($ch["gdsCharName"]) {
							case "Цвет корпуса" :
								$row->setCellAtIndex(WriterEntityFactory::createCell($ch["gdsCharVal"]), $colindex);	
								break;
							}
					}
				}

			}
		}

	} else {
		$row->setCellAtIndex(WriterEntityFactory::createCell($res_array["status"]["code"] . " " . $res_array["status"]["message"]), 1);	
	}
	return $res_array["status"]["code"] == "200";
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

// Create a client with a base URI
//	$client = new Client(["base_uri" => $etm_array["source"]]);
//	$response = $client->request('POST', 'user/login?log='.$etm_array["login"].'&pwd='.$etm_array["password"]);
//	try {
//		$response = $client->request('POST', 'user/login?log=ingstroysnab2&pwd=pahj9808');
//	} catch (\GuzzleHttp\Exception\BadResponseException $e) {
//		return $e->getResponse()->getBody()->getContents();
//	}
	$res = file_get_contents($etm_array["source"] . 'user/login?log='.$etm_array["login"] . '&pwd='. $etm_array["password"]);
	$res_array = json_decode($res, true);
	$session = $res_array["data"]["session"];
	//$code = $response->getStatusCode(); // 200
//	$reason = $response->getReasonPhrase(); // OK

	return $session;
}

function reserve() {
/*	
	$sheet->setCellValue('A1', 'Внешний Id');
	$sheet->setCellValue('B1', 'Название');
	$sheet->setCellValue('C1', 'Категория');
	$sheet->setCellValue('D1', 'Родительская категория');
	$sheet->setCellValue('E1', 'Производитель');
	$sheet->setCellValue('F1', 'Артикул');
	$sheet->setCellValue('G1', 'Цвет');
	$sheet->setCellValue('H1', 'EAN');
	$sheet->setCellValue('I1', 'Внешний Id');
	$sheet->setCellValue('J1', 'Внешний Id');
	$sheet->setCellValue('K1', 'Внешний Id');
	$sheet->setCellValue('L1', 'Внешний Id');
	$sheet->setCellValue('M1', 'Внешний Id');
	$sheet->setCellValue('N1', 'Вес');
	$sheet->setCellValue('O1', 'Длина');
	$sheet->setCellValue('P1', 'Ширина');
	$sheet->setCellValue('Q1', 'Высота');
*/
}

?>