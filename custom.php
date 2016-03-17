<?php
	class custom extends def_module {
		public function cms_callMethod($method_name, $args) {
			return call_user_func_array(Array($this, $method_name), $args);
		}
		
		public function __call($method, $args) {
			throw new publicException("Method " . get_class($this) . "::" . $method . " doesn't exists");
		}
		//TODO: Write your own macroses here

		function makeThumbnailFull(
			$path,
			$width,
			$height,
			$crop = true,
			$cropside = 5,
			$isLogo = false,
			$quality = 100,
			$isSharpen = false
		) {

			$thumbs_path = './images/cms/thumbs/';

			$img_file = new umiImageFile($path);

			$file_name = $img_file->getFileName();
			$file_ext = strtolower($img_file->getExt());
			$file_ext = ($file_ext == 'bmp' ? 'jpg' : $file_ext);

			$ext_array = Array('gif', 'jpeg', 'jpg', 'png', 'bmp');

			if (!in_array($file_ext, $ext_array)) {
				return "";
			}

			$file_name = substr($file_name, 0, (strlen($file_name) - (strlen($file_ext) + 1)));
			$new_dir_name = sha1($img_file->getDirName());

			if (!is_dir($thumbs_path . $new_dir_name)) {
				mkdir($thumbs_path . $new_dir_name, 0755, true);
			}

			$new_file_name = $file_name . '_' . $width . '_' . $height . '_' . $cropside . '_' . $quality . "." . $file_ext;
			$new_file_path = $thumbs_path . $new_dir_name . '/' . $new_file_name;

			if (!file_exists($new_file_path) || filemtime($new_file_path) < filemtime($path)) {
				if (file_exists($new_file_path)) {
					unlink($new_file_path);
				}

				/*
                 * Если установлен Imagick, то все картинки обрабатываются через него,
                 * в противном случае через старый механизм.
                 */
				if( class_exists( 'Imagick' ) ) {

					$img = new Imagick($path);

					$img->cropThumbnailImage($width, $height);
					if ($isSharpen) {
						$img->adaptiveSharpenImage(2, 1);
					}

					$img->setImageCompression(Imagick::COMPRESSION_JPEG);
					$img->setImageCompressionQuality(98);

					$img->setImageFormat('jpg');
					$img->writeImage($new_file_path);
					$img->destroy();

				}else {

					$src_width = $img_file->getWidth();
					$src_height = $img_file->getHeight();

					if (!($src_width && $src_height)) {
						throw new coreException(getLabel('error-image-corrupted', null, $path));
					}

					if ($height == "auto") {
						$real_height = (int)round($src_height * ($width / $src_width));
						$height = $real_height;
						$real_width = (int)$width;
					} else {

						if ($width == "auto") {
							$real_width = (int)round($src_width * ($height / $src_height));
							$width = $real_width;
						} else {
							$real_width = (int)$width;
						}

						$real_height = (int)$height;
					}

					$offset_h = 0;
					$offset_w = 0;

					if (!intval($width) || !intval($height)) {
						$crop = false;
					}

					if ($crop) {

						$width_ratio = $src_width / $width;
						$height_ratio = $src_height / $height;

						if ($width_ratio > $height_ratio) {

							$offset_w = round(($src_width - $width * $height_ratio) / 2);
							$src_width = round($width * $height_ratio);

						} elseif ($width_ratio < $height_ratio) {
							$offset_h = round(($src_height - $height * $width_ratio) / 2);
							$src_height = round($height * $width_ratio);
						}

						if ($cropside) {

							switch ($cropside):
								case 1:
									$offset_w = 0;
									$offset_h = 0;
									break;
								case 2:
									$offset_h = 0;
									break;
								case 3:
									$offset_w += $offset_w;
									$offset_h = 0;
									break;
								case 4:
									$offset_w = 0;
									break;
								case 5:
									break;
								case 6:
									$offset_w += $offset_w;
									break;
								case 7:
									$offset_w = 0;
									$offset_h += $offset_h;
									break;
								case 8:
									$offset_h += $offset_h;
									break;
								case 9:
									$offset_w += $offset_w;
									$offset_h += $offset_h;
									break;
							endswitch;

						}
					}

					$thumb = imagecreatetruecolor($real_width, $real_height);
					$source_array = $img_file->createImage($path);
					$source = $source_array['im'];

					if ($width * 4 < $src_width && $height * 4 < $src_height) {

						$_TMP = array();
						$_TMP['width'] = round($width * 4);
						$_TMP['height'] = round($height * 4);
						$_TMP['image'] = imagecreatetruecolor($_TMP['width'], $_TMP['height']);

						if ($file_ext == 'gif') {

							$_TMP['image_white'] = imagecolorallocate($_TMP['image'], 255, 255, 255);
							imagefill($_TMP['image'], 0, 0, $_TMP['image_white']);
							imagecolortransparent($_TMP['image'], $_TMP['image_white']);
							imagealphablending($source, true);
							imagealphablending($_TMP['image'], true);

						} else {

							imagealphablending($_TMP['image'], false);
							imagesavealpha($_TMP['image'], true);

						}

						imagecopyresampled($_TMP['image'], $source, 0, 0, $offset_w, $offset_h, $_TMP['width'],
							$_TMP['height'], $src_width, $src_height);
						imageDestroy($source);
						$source = $_TMP['image'];
						$src_width = $_TMP['width'];
						$src_height = $_TMP['height'];
						$offset_w = 0;
						$offset_h = 0;
						unset($_TMP);

					}

					if ($file_ext == 'gif') {

						$thumb_white_color = imagecolorallocate($thumb, 255, 255, 255);
						imagefill($thumb, 0, 0, $thumb_white_color);
						imagecolortransparent($thumb, $thumb_white_color);
						imagealphablending($source, true);
						imagealphablending($thumb, true);

					} else {

						imagealphablending($thumb, false);
						imagesavealpha($thumb, true);

					}

					imagecopyresampled($thumb, $source, 0, 0, $offset_w, $offset_h, $width, $height, $src_width,
						$src_height);

					if ($isSharpen) {
						$thumb = makeThumbnailFullUnsharpMask($thumb, 80, .5, 3);
					}

					switch ($file_ext) {
						case 'gif':
							$res = imagegif($thumb, $new_file_path);
							break;
						case 'png':
							$res = imagepng($thumb, $new_file_path);
							break;
						default:
							$res = imagejpeg($thumb, $new_file_path, $quality);
					}

					if (!$res) {
						throw new coreException(getLabel('label-errors-16008'));
					}

					imageDestroy($source);
					imageDestroy($thumb);

					if ($isLogo) {
						umiImageFile::addWatermark($new_file_path);
					}
				}
			}

			$value = new umiImageFile($new_file_path);
			$arr = Array();

			$arr['size'] = $value->getSize();
			$arr['filename'] = $value->getFileName();
			$arr['filepath'] = $value->getFilePath();
			$arr['src'] = $value->getFilePath(true);
			$arr['ext'] = $value->getExt();
			$arr['width'] = $value->getWidth();
			$arr['height'] = $value->getHeight();

			if (cmsController::getInstance()->getCurrentMode() == "admin") {
				$arr['src'] = str_replace("&", "&amp;", $arr['src']);
			}

			return $arr;
		}
	};
?>