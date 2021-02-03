<?php
/**
 * @filesource modules/dms/models/write.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Dms\Write;

use Gcms\Login;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;
use Kotchasan\Number;

/**
 * module=dms-write
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * อ่านข้อมูลรายการที่เลือก
     * ถ้า $id = 0 หมายถึงรายการใหม่
     *
     * @param int   $id    ID
     *
     * @return object|null คืนค่าข้อมูล object ไม่พบคืนค่า null
     */
    public static function get($id)
    {
        if (empty($id)) {
            // ใหม่
            return (object) array(
                'id' => 0,
            );
        } else {
            // แก้ไข อ่านรายการที่เลือก
            return static::createQuery()
                ->from('dms')
                ->where(array('id', $id))
                ->first();
        }
    }

    /**
     * บันทึกข้อมูลที่ส่งมาจากฟอร์ม write.php.
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token, สามารถอัปโหลดได้
        if ($request->initSession() && $request->isSafe() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_upload_dms')) {
                try {
                    // ค่าที่ส่งมา
                    $save = array(
                        'document_no' => $request->post('document_no')->topic(),
                        'create_date' => $request->post('create_date')->date(),
                        'topic' => $request->post('topic')->topic(),
                        'detail' => $request->post('detail')->textarea(),
                    );
                    // ตรวจสอบรายการที่เลือก
                    $index = self::get($request->post('id')->toInt());
                    if ($index) {
                        foreach (Language::get('DMS_CATEGORIES') as $k => $label) {
                            $save[$k] = \Gcms\Category::save($k, $request->post($k.'_text')->topic());
                        }
                        // table
                        $table_dms = $this->getTableName('dms');
                        $table_dms_files = $this->getTableName('dms_files');
                        // Database
                        $db = $this->db();
                        if ($index->id == 0) {
                            $save['id'] = $db->getNextId($table_dms);
                        } else {
                            $save['id'] = $index->id;
                        }
                        if ($save['topic'] == '') {
                            // ไม่ได้กรอก topic
                            $ret['ret_topic'] = 'Please fill in';
                        }
                        if ($save['document_no'] == '') {
                            // ไม่ได้กรอกเลขที่เอกสาร
                            $save['document_no'] = Number::printf(self::$cfg->dms_format_no, $save['id']);
                        }
                        // ค้นหาเลขที่เอกสารซ้ำ
                        $search = $db->first($table_dms, array('document_no', $save['document_no']));
                        if ($search && ($index->id == 0 || $index->id != $search->id)) {
                            $ret['ret_document_no'] = Language::replace('This :name already exist', array(':name' => Language::get('Document number')));
                        }
                        $files = array();
                        if (empty($ret)) {
                            // วันนี้
                            $create_date = date('Y-m-d H:i:s');
                            // ไดเร็คทอรี่เก็บไฟล์
                            $dir = 'dms/'.$save['id'].'/';
                            $dir2 = ROOT_PATH.DATA_FOLDER.$dir;
                            // อัปโหลดไฟล์
                            foreach ($request->getUploadedFiles() as $item => $file) {
                                /* @var $file \Kotchasan\Http\UploadedFile */
                                if (preg_match('/^([a-z0-9_]+)(\[[0-9]+\])?$/', $item, $match)) {
                                    if ($file->hasUploadFile()) {
                                        if (!File::makeDirectory(ROOT_PATH.DATA_FOLDER.'dms/') || !File::makeDirectory($dir2)) {
                                            // ไดเรคทอรี่ไม่สามารถสร้างได้
                                            $ret['ret_'.$match[1]] = sprintf(Language::get('Directory %s cannot be created or is read-only.'), 'dms/'.$save['id'].'/');
                                        } elseif (!$file->validFileExt(self::$cfg->dms_file_typies)) {
                                            // ชนิดของไฟล์ไม่ถูกต้อง
                                            $ret['ret_'.$match[1]] = Language::get('The type of file is invalid');
                                        } elseif (self::$cfg->dms_upload_size > 0 && $file->getSize() > self::$cfg->dms_upload_size) {
                                            // ขนาดของไฟล์ใหญ่เกินไป
                                            $ret['ret_'.$match[1]] = Language::get('The file size larger than the limit');
                                        } else {
                                            // อัปโหลด ชื่อไฟล์แบบสุ่ม
                                            $ext = $file->getClientFileExt();
                                            $file_upload = uniqid().'.'.$ext;
                                            while (file_exists($dir2.$file_upload)) {
                                                $file_upload = uniqid().'.'.$ext;
                                            }
                                            try {
                                                $file->moveTo($dir2.$file_upload);
                                                $topic = preg_replace('/\\.'.$ext.'$/', '', $file->getClientFilename());
                                                $files[] = array(
                                                    'dms_id' => $save['id'],
                                                    'ext' => $ext,
                                                    'topic' => $topic,
                                                    'name' => preg_replace('/[,;:_\-]{1,}/', '_', $topic),
                                                    'size' => $file->getSize(),
                                                    'file' => $dir.$file_upload,
                                                    'create_date' => $create_date,
                                                );
                                            } catch (\Exception $exc) {
                                                // ไม่สามารถอัปโหลดได้
                                                $ret['ret_'.$match[1]] = Language::get($exc->getMessage());
                                            }
                                        }
                                    } elseif ($file->hasError()) {
                                        // ข้อผิดพลาดการอัปโหลด
                                        $ret['ret_'.$match[1]] = Language::get($file->getErrorMessage());
                                    }
                                }
                            }
                        }
                        if ($index->id == 0 && empty($files)) {
                            // ใหม่ ไม่ได้เลือกไฟล์
                            $ret['ret_file'] = 'Please browse file';
                        }
                        if (empty($ret)) {
                            if ($index->id == 0) {
                                // ใหม่
                                $save['member_id'] = $login['id'];
                                $db->insert($table_dms, $save);
                            } else {
                                // แก้ไข
                                $db->update($table_dms, $save['id'], $save);
                            }
                            if (!empty($files)) {
                                foreach ($files as $item) {
                                    // ไฟล์
                                    $db->insert($table_dms_files, $item);
                                }
                            }
                            // คืนค่า
                            $ret['alert'] = Language::get('Saved successfully');
                            $ret['location'] = $request->getUri()->postBack('index.php', array('module' => 'dms-setup'));
                            // เคลียร์
                            $request->removeToken();
                        }
                    }
                } catch (\Kotchasan\InputItemException $e) {
                    $ret['alert'] = $e->getMessage();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }
}
