<?php
/**
 * @filesource modules/dms/models/home.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Dms\Home;

use Kotchasan\Database\Sql;

/**
 * โมเดลสำหรับอ่านข้อมูลแสดงในหน้า  Home
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * เอกสารใหม่
     *
     * @return object
     */
    public static function getNew($login)
    {
        $search = static::createQuery()
            ->from('dms_files F')
            ->join('dms A', 'INNER', array('A.id', 'F.dms_id'))
            ->where(array(Sql::DATEDIFF('A.create_date', Sql::NOW()), '<', 30))
            ->notExists('dms_download', array(
                array('file_id', 'F.id'),
                array('member_id', $login['id']),
            ))
            ->first(Sql::COUNT('F.id', 'count'));
        if ($search) {
            return $search->count;
        }

        return 0;
    }
}
