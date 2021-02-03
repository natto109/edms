<?php
/**
 * @filesource modules/dms/models/setup.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Dms\Setup;

use Gcms\Login;
use Kotchasan\File;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=dms-setup
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query ข้อมูลสำหรับส่งให้กับ DataTable
     *
     * @return \Kotchasan\Database\QueryBuilder
     */
    public static function toDataTable()
    {
        return static::createQuery()
            ->select('A.id', 'A.create_date', 'A.document_no', 'A.topic', 'A.department', 'A.cabinet')
            ->from('dms A');
    }

    /**
     * รับค่าจาก action
     *
     * @param Request $request
     */
    public function action(Request $request)
    {
        $ret = array();
        // session, referer, สามารถอัปโหลดได้, ไม่ใช่สมาชิกตัวอย่าง
        if ($request->initSession() && $request->isReferer() && $login = Login::isMember()) {
            if (Login::checkPermission($login, 'can_upload_dms') && Login::notDemoMode($login)) {
                // รับค่าจากการ POST
                $id = $request->post('id')->toString();
                $action = $request->post('action')->toString();
                // ตรวจสอบค่าที่ส่งมา
                if (preg_match('/^[0-9,]+$/', $id)) {
                    if ($action === 'delete') {
                        // ลบ
                        $ids = explode(',', $id);
                        foreach ($ids as $id) {
                            // ลบไฟล์
                            File::removeDirectory(ROOT_PATH.DATA_FOLDER.'dms/'.$id.'/');
                        }
                        // ลบข้อมูล
                        $this->db()->delete($this->getTableName('dms'), array('id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_files'), array('dms_id', $ids), 0);
                        $this->db()->delete($this->getTableName('dms_download'), array('dms_id', $ids), 0);
                        // reload
                        $ret['location'] = 'reload';
                    }
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
