<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ERROR);
set_time_limit(300); ini_set('max_execution_time', '300'); //300 seconds = 5 minutes

require 'vendor/autoload.php';
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Common\Entity\Row;

//$groupChars = array(); 											// словарь характеристик по группам (классам)
																	// [XXXX классиф. товара][индекс] = характеристика
$mainChars = array_fill(0, 20, "reserve");							// плоский словарь характеристик [index] = характеристика

$articleCol = 5;
$resArray = array();												// результирующий массив

if (!file_exists(__DIR__ ."/" ."config.ini")) {
	echo "Не обнаружен config.ini -- работа невозможна.";
	die;
}
$ini_array = parse_ini_file("config.ini", true);
makeExcelfile();

function makeArrays($session, $sourcefile) {
	global $articleCol, $resArray, $mainChars;	
	$reader = ReaderEntityFactory::createXLSXReader();
	$reader = ReaderEntityFactory::createReaderFromFile($sourcefile);
	$reader->open($sourcefile);
	$mainChars[12] = "Страна изготовления";
	$mainChars[13] = "Масса, кг";
	$mainChars[14] = "Длина, мм";
	$mainChars[15] = "Ширина, мм";
	$mainChars[16] = "Высота, мм";
	$mainChars[17] = "Изображение 1";
	$mainChars[18] = "Изображение 2";
	$mainChars[19] = "Изображение 3";

	foreach ($reader->getSheetIterator() as $sheet) {
		foreach ($sheet->getRowIterator() as $rowindex=>$row) {
			if ($rowindex == 1) { 											// первую строку просто копируем
				for ($i = 0; $i < 12; $i++) {
					$cell = $row->getCellAtIndex($i);
					if ($cell !== null) 
						$mainChars[$i] = $row->getCellAtIndex($i)->getValue();
					else
						$mainChars[$i] = "";				
				}				
				continue;
			}			
			if ($row->getCellAtIndex($articleCol) !== null)					// если артикул - не пустой
				$article = $row->getCellAtIndex($articleCol)->getValue();
			else
				continue;													// просто пропускаем строку
			$myrow = array();
			for ($i = 0; $i < 18; $i++) {
				$cell = $row->getCellAtIndex($i);
				if ($cell !== null) 
					$myrow[] = $row->getCellAtIndex($i)->getValue();
				else
					$myrow[] = "";				
			}

			if (strpos($article, " ") === false) {							// если нет пробелов в артикуле
				$status = setGoodsProperties($session, $article, $myrow);
				if ($status == "200") {
					$resArray[] = $myrow;
				} else {
					$notfound[] = $article;
				}
			} else {
												// если есть пробелы, то возвращаем исходную строку
			}
			// if ($rowindex > 100) break;
		}
	}
	$reader->close();
}

function makeExcelfile() {
	global $ini_array;
	global $articleCol, $groupChars, $mainChars, $resArray;	
	$time1 = date("d-m-Y H:i:s");
	echo "Старт $time1<br> ";
	$exp_array = $ini_array["Exporter"];
	$xlsxname = $exp_array["tempfolder"] . "/" . $exp_array["xlsxgoodsfilename"];

	$etm_array = $ini_array["ETM"]; 
	$session = getsession($etm_array);

	$in_array = $ini_array["In"]; 
	$sourcefile = $exp_array["sourcefolder"] . "/" . $in_array["goodssourcefile"];

	if (!file_exists(__DIR__ ."/" .$sourcefile)) {
		die("<br>Не обнаружен исходный файл $sourcefile -- работа невозможна.");
	}

	makeArrays($session, $sourcefile);

	// создаем массив с доп.характеристиками
	$qrows = count($mainChars);
//var_dump($mainChars);

	$writer = WriterEntityFactory::createXLSXWriter();
	$writer->setTempFolder($exp_array["tempfolder"]);
	$writer->openToFile($xlsxname);
	$notfound = array();

	// пишем заголовок 
	$rowFromValues = WriterEntityFactory::createRowFromArray($mainChars);
	$writer->addRow($rowFromValues);

	foreach ($resArray as $rowindex=>$item) {
		$myrow = array_fill(0, $qrows, "");
		for ($i = 0; $i < 16; $i++) {
			$myrow[$i] = $item[$i];
		}
		foreach ($item["Chars"] as $char) {
			if (in_array($char["gdsCharName"], $mainChars)) {
				$key = array_search($char["gdsCharName"], $mainChars);
				$myrow[$key] = $char["gdsCharVal"];
				if (is_numeric($char["gdsCharVal"])) {
					if (is_float($char["gdsCharVal"]))
						$myrow[$key] = floatval($char["gdsCharVal"]);
					if (is_int($char["gdsCharVal"]))
						$myrow[$key] = intval($char["gdsCharVal"]);
				}
			}			
		}
		$rowImg = 17;
		foreach ($item["Img"] as $img) {
			$myrow[$rowImg] =  "https://cdn.etm.ru" . $img["gdsImgSrc"]; 
			$rowImg = $rowImg + 1;
			if ($rowImg > 19) break;
		}

		$rowFromValues = WriterEntityFactory::createRowFromArray($myrow);
		$writer->addRow($rowFromValues);
//var_dump($myrow);		
	}
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

function setGoodsProperties($session, $article, &$row) {
	global $ini_array, $groupChars, $mainChars;

	$res_array = makeRequest($article, $session, "");
	if ($res_array == false) return false;
	if ($res_array["status"]["code"] == "200") {
		$arrData = $res_array["data"];
		$class =  mb_substr($arrData["gdsInfoClass81"], 0 , 2);
		$row[0] = $arrData["gdsCode"];
		if (array_key_exists('barcodes',$arrData))
			$row[7] = strval($arrData["barcodes"][0]["val"]);
		$arrMeas = $arrData["gdsChars"];
		$row[12] = $arrData["gdsNameCountry"];
		foreach ($arrMeas as $ch) {
			switch ($ch["gdsCharName"]) {
				case "Масса, кг" :
					$row[13] =  floatval($ch["gdsCharVal"]);
					break;
				case "Ширина, мм" :
					$row[15] =  floatval($ch["gdsCharVal"]);
					break;
				case "Длина, мм" :
					$row[14] = floatval($ch["gdsCharVal"]);
					break;
				case "Высота, мм" :
					$row[16] =  floatval($ch["gdsCharVal"]);
					break;
				case "Цвет корпуса" :
					$row[6] =  $ch["gdsCharVal"];
					break;
			}
		}
		foreach ($arrMeas as $ch) {
			if (!in_array($ch["gdsCharName"], $mainChars)) {
				$mainChars[] = $ch["gdsCharName"];
			}
			$row["Chars"][] = $ch;
		}
		$row["Img"] = $arrData["gdsImages"];

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
		echo "HTTP-запрос $source не сработал. Ошибка: " . $error['message'];
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

?>