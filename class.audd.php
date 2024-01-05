<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2024 Uwe Steinmann <uwe@steinmann.cx>
*  All rights reserved
*
*  This script is part of the SeedDMS project. The SeedDMS project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once("inc/inc.ClassConversionServiceBase.php");
require_once(__DIR__."/class.PhpMp3.php");

/**
 * Convert audd extension
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  audd
 */
class SeedDMS_ExtAudd extends SeedDMS_ExtBase {

	/**
	 * Initialization
	 *
	 * Use this method to do some initialization like setting up the hooks
	 * You have access to the following global variables:
	 * $GLOBALS['settings'] : current global configuration
	 * $GLOBALS['settings']->_extensions['index_info'] : configuration of this extension
	 * $GLOBALS['LANG'] : the language array with translations for all languages
	 * $GLOBALS['SEEDDMS_HOOKS'] : all hooks added so far
	 */
	function init() { /* {{{ */
		$GLOBALS['SEEDDMS_HOOKS']['view']['style'][] = new SeedDMS_ExtAudd_DocumentPreview;
		$GLOBALS['SEEDDMS_HOOKS']['initConversion'][] = new SeedDMS_ExtAudd_InitConversion;
		$GLOBALS['SEEDDMS_HOOKS']['view']['clearCache'][] = new SeedDMS_ExtAudd_ClearCache_View;
		$GLOBALS['SEEDDMS_HOOKS']['controller']['clearCache'][] = new SeedDMS_ExtAudd_ClearCache_Controller;
	} /* }}} */

	function main() { /* {{{ */
	} /* }}} */
}

/**
 * Class implementing a conversion service from spotify album image to png
 *
 * It just takes the album image file from spotify and returns it as a preview.
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage audd
 */
class SeedDMS_ExtAudd_ConversionServiceToPng extends SeedDMS_ConversionServiceBase { /* {{{ */
	/**
	 * configuration
	 */
	protected $conf;

	/**
	 * DMS
	 */
	protected $dms;

	public function __construct($dms, $conf) { /* {{{ */
		$this->dms = $dms;
		$this->conf = $conf;
	} /* }}} */

	public function getInfo() { /* {{{ */
		return "Convert with service provided by extension audd";
	} /* }}} */

	public function getAdditionalParams() { /* {{{ */
		return [
			['name'=>'width', 'type'=>'number', 'description'=>'Width of converted image'],
//			['name'=>'page', 'type'=>'number', 'description'=>'Page of Pdf document'],
		];
	} /* }}} */

	public function convert($infile, $target = null, $params = array()) { /* {{{ */
		$start = microtime(true);

		// Nasty hack to extract content from infile
		$tmp = explode(DIRECTORY_SEPARATOR, substr($infile, strlen($this->dms->contentDir)));
		$docid = (int) $tmp[0];
		$version = (int) strtok($tmp[1], '.');
		if(!($document = $this->dms->getDocument($docid)))
			return false;
		if(!($content = $document->getContentByVersion($version)))
			return false;

		$tmpdir = addDirSep($this->conf->_cacheDir).'audd';
		if (!file_exists($tmpdir))
			if (!SeedDMS_Core_File::makeDir($tmpdir)) return false;

		$datafile = $tmpdir.DIRECTORY_SEPARATOR.$content->getId().'.json';
		if(!file_exists($datafile)) {
			/* Conversion service does not get missing data from Audd.io
			 * because the number of free requests is very limited.
			 */
			if(0) {
				$mp3 = new PhpMp3($dms->contentDir . $content->getPath());
				$mp3_1 = $mp3->extract(10,10);
				$tmpfile = tempnam(sys_get_temp_dir(), 'audd');
				$mp3_1->save($tmpfile);
				$cFile = curl_file_create($tmpfile);
				if(!empty($this->conf->_extensions['audd']['apitoken']))
					$apitoken = $this->conf->_extensions['audd']['apitoken'];
				else
					$apitoken = '';
				$post = array('api_token' => $apitoken, 'file'=> $cFile, 'return'=>'musicbrainz,spotify,apple_music');
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://api.audd.io/');
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				$result=curl_exec($ch);
				$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				if($result) {
					$data = json_decode($result, true);
					if($data['status'] == 'success')
						file_put_contents($datafile, $result);
				}
			} else {
				$data = [];
			}
		} else {
			$result = file_get_contents($datafile);
			$data = json_decode($result, true);
		}

		if(isset($data['status']) && ($data['status'] == 'success')) {
			if(isset($data['result']['spotify']['album']['images'])) {
				$imgdata = file_get_contents($data['result']['spotify']['album']['images'][0]['url']);
				$im = @imagecreatefromstring($imgdata);
				if($im) {
					$this->success = true;
					$width = imagesx($im);
					if(!empty($params['width']))
						$im = imagescale($im, min((int) $params['width'], $width));
					$end = microtime(true);
					if($this->logger) {
						$this->logger->log('Conversion from '.$this->from.' to '.$this->to.' with audd service took '.($end-$start).' sec.', PEAR_LOG_INFO);
					}
					if($target) {
						return imagepng($im, $target);
					} else {
						ob_start();
						echo imagepng($im);
						$image = ob_get_clean();
						return $image;
					}
				}
			}
		}
		return false;
	} /* }}} */
} /* }}} */

/**
 * Class implementing a conversion service from auddd to txt
 *
 * It just extracts the json data and reads some fields.
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage audd
 */
class SeedDMS_ExtAudd_ConversionServiceToTxt extends SeedDMS_ConversionServiceBase { /* {{{ */
	/**
	 * configuration
	 */
	protected $conf;

	/**
	 * DMS
	 */
	protected $dms;

	public function __construct($dms, $conf) { /* {{{ */
		$this->dms = $dms;
		$this->conf = $conf;
	} /* }}} */

	public function getInfo() { /* {{{ */
		return "Convert with service provided by extension audd";
	} /* }}} */

	public function getAdditionalParams() { /* {{{ */
		return [];
	} /* }}} */

	public function convert($infile, $target = null, $params = array()) { /* {{{ */
		$start = microtime(true);

		$txt = '';

		// Nasty hack to extract content from infile
		$tmp = explode(DIRECTORY_SEPARATOR, substr($infile, strlen($this->dms->contentDir)));
		$docid = (int) $tmp[0];
		$version = (int) strtok($tmp[1], '.');
		if(!($document = $this->dms->getDocument($docid)))
			return false;
		if(!($content = $document->getContentByVersion($version)))
			return false;

		$tmpdir = addDirSep($this->conf->_cacheDir).'audd';
		if (!file_exists($tmpdir))
			if (!SeedDMS_Core_File::makeDir($tmpdir)) return false;

		$datafile = $tmpdir.DIRECTORY_SEPARATOR.$content->getId().'.json';
		if(!file_exists($datafile)) {
			/* Conversion service does not get missing data from Audd.io
			 * because the number of free requests is very limited.
			 */
			if(0) {
				$mp3 = new PhpMp3($dms->contentDir . $content->getPath());
				$mp3_1 = $mp3->extract(10,10);
				$tmpfile = tempnam(sys_get_temp_dir(), 'audd');
				$mp3_1->save($tmpfile);
				$cFile = curl_file_create($tmpfile);
				if(!empty($this->conf->_extensions['audd']['apitoken']))
					$apitoken = $this->conf->_extensions['audd']['apitoken'];
				else
					$apitoken = '';
				$post = array('api_token' => $apitoken, 'file'=> $cFile, 'return'=>'musicbrainz,spotify,apple_music');
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://api.audd.io/');
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				$result=curl_exec($ch);
				$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				if($result) {
					$data = json_decode($result, true);
					if($data['status'] == 'success')
						file_put_contents($datafile, $result);
				}
			} else {
				$data = [];
			}
		} else {
			$result = file_get_contents($datafile);
			$data = json_decode($result, true);
		}

		$end = microtime(true);

		if($this->logger) {
			$this->logger->log('Conversion from '.$this->from.' to '.$this->to.' with audd took '.($end-$start).' sec.', PEAR_LOG_DEBUG);
		}
		if(isset($data['status']) && ($data['status'] == 'success')) {
			$txt = $data['result']['artist']."\n";
			$txt .= $data['result']['title']."\n";
			$txt .= $data['result']['album']."\n";
			$this->success = true;
			if($target) {
				return file_put_contents($target, $txt);
			} else {
				return $txt;
			}
		}
		return false;
	} /* }}} */
} /* }}} */

/**
 * Class containing methods for hooks when the conversion service is initialized
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  audd
 */
class SeedDMS_ExtAudd_InitConversion { /* {{{ */

	/**
	 * Hook returning further conversion services
	 */
	public function getConversionServices($params) { /* {{{ */
		$dms = $params['dms'];
		$conf = !empty($params['settings']) ? $params['settings'] : [];
		$services = [];
		$service = new SeedDMS_ExtAudd_ConversionServiceToPng($dms, $conf);
		$service->from = 'audio/mpeg';
		$service->to = 'image/png';
		$services[] = $service;

		$service = new SeedDMS_ExtAudd_ConversionServiceToTxt($dms, $conf);
		$service->from = 'audio/mpeg';
		$service->to = 'text/plain';
		$services[] = $service;
		return $services;
	} /* }}} */

} /* }}} */

class SeedDMS_ExtAudd_DocumentPreview { /* {{{ */

	private static function time2sec($l) { /* {{{ */
		$l = round($l/1000);
		$m = (int) ($l/60);
		$s = (int) ($l%60);
		return $m.':'.$s;
	} /* }}} */

	/**
	 * Hook for additional document previews
	 */
	function postDocumentPreview($view, $content) { /* {{{ */
		$settings = $view->getParam('settings');

		$document = $content->getDocument();
		$dms = $document->getDMS();
		if($content->getMimeType() == 'audio/mpeg') {
			$txt = '';

			$tmpdir = addDirSep($settings->_cacheDir).'audd';
			if (!file_exists($tmpdir))
				if (!SeedDMS_Core_File::makeDir($tmpdir)) return false;

			$iscached = false;
			$datafile = $tmpdir.DIRECTORY_SEPARATOR.$content->getId().'.json';
			if(!file_exists($datafile)) {
				$mp3 = new PhpMp3($dms->contentDir . $content->getPath());
				$startsec = 30;
				$numsecs = 10;
				if(!empty($settings->_extensions['audd']['startsec']) && is_numeric($settings->_extensions['audd']['startsec']))
					$startsec = (int) $settings->_extensions['audd']['startsec'];
				if(!empty($settings->_extensions['audd']['numsecs']) && is_numeric($settings->_extensions['audd']['numsecs']))
					$numsecs = (int) $settings->_extensions['audd']['numsecs'];
				$mp3_1 = $mp3->extract($startsec, $numsecs);
				$tmpfile = tempnam(sys_get_temp_dir(), 'audd');
				$mp3_1->save($tmpfile);
				$cFile = curl_file_create($tmpfile);
				if(!empty($settings->_extensions['audd']['apitoken']))
					$apitoken = $settings->_extensions['audd']['apitoken'];
				else
					$apitoken = '';
				$post = array('api_token' => $apitoken, 'file'=> $cFile, 'return'=>'musicbrainz,spotify,apple_music');
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, 'https://api.audd.io/');
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				$result=curl_exec($ch);
				$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close ($ch);
				if($result) {
					$data = json_decode($result, true);
					if($data['status'] == 'success')
						file_put_contents($datafile, $result);
				}
			} else {
				$iscached = true;
				$result = file_get_contents($datafile);
				$data = json_decode($result, true);
			}

			if($data['status'] == 'success') {
				$txt .= "<table class=\"table table-sm table-condensed\">";
				$txt .= "<tr>";
				$txt .= "<td>".getMLText('audd_artist')."</td>";
				$txt .= "<td>".$data['result']['artist']."</td>";
				$txt .= "</tr>";
				$txt .= "<tr>";
				$txt .= "<td>".getMLText('audd_title')."</td>";
				$txt .= "<td>".$data['result']['title']."</td>";
				$txt .= "</tr>";
				$txt .= "<tr>";
				$txt .= "<td>".getMLText('audd_album')."</td>";
				$txt .= "<td>".$data['result']['album']."</td>";
				$txt .= "</tr>";
				$txt .= "<tr>";
				$txt .= "<td>".getMLText('audd_release_date')."</td>";
				$txt .= "<td>".$data['result']['release_date']."</td>";
				$txt .= "</tr>";
				$txt .= "<tr>";
				$txt .= "<td>".getMLText('audd_song_link')."</td>";
				$txt .= "<td><a target=\"_blank\" href=\"".$data['result']['song_link']."\">".$data['result']['song_link']."</a></td>";
				$txt .= "</tr>";
				$txt .= "</table>";
				if($iscached) {
					$txt .= "<p>".getMLText('audd_data_from_cache')."</p>";
				}
				if(isset($data['result']['spotify']['album']['images'])) {
					$txt .= "<img title=\"".getMLText('audd_spotify_cover')."\" src=\"".$data['result']['spotify']['album']['images'][0]['url']."\">";
				}
				if(isset($data['result']['musicbrainz'])) {
					$txt .= "<h4>Music Brainz Releases</h4>";
					$countries = explode(',', $settings->_extensions['audd']['countries']);
					$txt .= "<table class=\"table table-sm table-condensed\">";
					foreach($data['result']['musicbrainz'] as $item) {
						foreach($item['releases'] as $rel) {
							if($countries && (empty($rel['country']) || in_array($rel['country'], $countries))) {
								$imgurl = '';
								/*
								$ch = curl_init();
								curl_setopt($ch, CURLOPT_URL, 'https://coverartarchive.org/release/'.$rel['id'].'');
								curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/json'));
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
								curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
								$result=curl_exec($ch);
								$httpstatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
								curl_close ($ch);
								if($httpstatus == '200') {
									$data = json_decode($result, true);
									foreach($data['images'] as $image) {
										if($image['front'])
											$imgurl = $image['thumbnails']['250'];
									}
								}
								 */
								$m = '';
								if(!empty($rel['media'])) {
									foreach($rel['media'] as $media)
										if(!empty($media['track']))
											$m = $media['format'].' ('.$media['position'].'), Track '.$media['track'][0]['number'].'/'.$media['track-count'].', '.self::time2sec($media['track'][0]['length']);
								}
								$txt .= "<tr>";
								$txt .= "<td>".($imgurl ? "<img width=\"100\" src=\"".$imgurl."\">" : "")."</td>";
								$txt .= "<td><a href=\"https://musicbrainz.org/release/".$rel['id']."\" target=\"musicbrainz\">".$rel['title']."</a><br>".$m."</td>";
		//						$txt .= "<td>".$rel['track-count']."</td>";
								$txt .= "<td>".$rel['date']."</td>";
								$txt .= "<td>".$rel['country']."</td>";
								$txt .= "</tr>";
							}
						}
					}
					$txt .= "</table>";
				}
			} else {
				$txt .= 'Error';
			}
//			$txt .= "<pre>";
//			$txt .= var_export($data, true);
//			$txt .= "</pre>";

			ob_start();
			$view->printAccordion2(getMLText('audd'), $txt);
			$txt = ob_get_clean();

			return $txt;
		} else {
			return null;
		}
	} /* }}} */

} /* }}} */

/**
 * Class containing methods for hooks when the cache shall be cleared
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  audd
 */
class SeedDMS_ExtAudd_ClearCache_View { /* {{{ */

	/**
	 * Hook for extending document content operations
	 */
	function additionalCache($view) { /* {{{ */
		$settings = $view->getParam('settings');

		$path = addDirSep($settings->_cacheDir).'audd';
		if(file_exists($path)) {
			$space = dskspace($path);
			$fi = new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS);
			$c = iterator_count($fi);
		} else {
			$space = $c = 0;
		}
		$cache['audd'] = ['audd', getMLText('audd'), $space, $c];
		return $cache;
	} /* }}} */

} /* }}} */

/**
 * Class containing methods for hooks when the cache shall be cleared
 *
 * @author  Uwe Steinmann <uwe@steinmann.cx>
 * @package SeedDMS
 * @subpackage  audd
 */
class SeedDMS_ExtAudd_ClearCache_Controller { /* {{{ */

	/**
	 * Hook for extending document content operations
	 */
	function clear($controller, $caches) { /* {{{ */
		$settings = $controller->getParam('settings');

		if(!empty($caches['audd'])) {
			$cmd = 'rm -rf '.addDirSep($settings->_cacheDir).'audd'.DIRECTORY_SEPARATOR.'*';
			system($cmd, $ret);
		}
		return null;
	} /* }}} */

} /* }}} */

