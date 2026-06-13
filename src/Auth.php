<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2011 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
namespace liliuwei\think;

/**
 * 权限认证类 (Auth)
 *
 * 功能特性：
 * 1. 基于规则进行认证，而非直接对节点进行认证。可以将节点名称作为规则名称来实现节点级别的权限控制。
 *    $auth = new Auth(); $auth->check('规则名称', '用户id');
 *
 * 2. 支持同时对多条规则进行认证，并可设置规则间的关系（OR 或 AND）。
 *    $auth = new Auth(); $auth->check('规则1,规则2', '用户id', 'and');
 *    第三个参数为 'and' 时，用户必须同时具备规则1和规则2的权限；
 *    为 'or' 时，具备任一规则即可。默认为 'or'。
 *
 * 3. 一个用户可以属于多个用户组（通过 auth_group_access 表关联）。
 *    每个用户组可以拥有多个权限规则（通过 auth_group 表的 rules 字段定义）。
 *
 * 4. 支持条件表达式规则。
 *    在 auth_rule 表中，当 type 为 1 时，condition 字段可定义表达式，如：{score}>5 and {score}<100
 *    表示用户的 score 字段值在 5-100 之间时，该规则才会通过验证。
 *
 */

// ====================== 数据库表结构说明 ======================
/*
-- ----------------------------
-- tp_admin 用户表
-- id: 主键，is_admin: 是否为管理员
-- ----------------------------
DROP TABLE IF EXISTS `tp_admin`;
CREATE TABLE `tp_admin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '管理员ID',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '管理员用户名',
  `fullname` varchar(50) NOT NULL DEFAULT '',
  `phone` varchar(20) NOT NULL DEFAULT '',
  `password_reset_token` varchar(255) NOT NULL DEFAULT '',
  `access_token` varchar(255) NOT NULL DEFAULT '',
  `expire_time` int(10) NOT NULL DEFAULT '0',
  `refresh_expires_time` int(255) NOT NULL DEFAULT '0',
  `refresh_token` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '管理员密码',
  `login_times` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '登陆次数',
  `login_ip` varchar(20) NOT NULL DEFAULT '' COMMENT 'IP地址',
  `login_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '登陆时间',
  `last_login_ip` varchar(255) NOT NULL DEFAULT '' COMMENT '上次登陆ip',
  `last_login_time` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '上次登陆时间',
  `user_agent` varchar(500) NOT NULL DEFAULT '' COMMENT 'user_agent',
  `create_time` int(10) unsigned NOT NULL DEFAULT '0',
  `update_time` int(10) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1可用0禁用',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- tp_auth_rule 权限规则表
-- id: 主键，name: 规则唯一标识, title: 规则中文名称, status: 1正常0禁用
-- condition: 规则表达式，为空表示只要规则存在即通过，不为空则按条件验证
-- menu_type: 菜单类型 (1-菜单项可点击, 2-菜单分组, 3-功能按钮)
-- is_menu: 是否在左侧菜单显示 (0-不显示, 1-显示)
-- pid: 上级规则ID，用于构建规则树
-- ----------------------------
DROP TABLE IF EXISTS `tp_auth_rule`;
CREATE TABLE `tp_auth_rule` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `name` char(80) NOT NULL DEFAULT '' COMMENT '规则唯一标识',
  `title` char(20) NOT NULL DEFAULT '' COMMENT '规则中文名称',
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：为1正常，为0禁用',
  `condition` char(100) NOT NULL DEFAULT '',
  `menu_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '菜单类型：1-菜单项(可点击跳转)，2-菜单分组(仅展开)，3-功能按钮(不显示菜单)',
  `is_menu` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否显示在左侧菜单：0-不显示，1-显示',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '上级id',
  `icon` varchar(42) NOT NULL DEFAULT 'fa fa-th-list' COMMENT '图标',
  `sort` int(11) NOT NULL DEFAULT '255' COMMENT '排序',
  `level` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `name` (`name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=274 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- tp_auth_group 用户组表
-- id: 主键， title: 用户组名称， rules: 用户组拥有的规则ID列表（逗号分隔）
-- status: 1正常0禁用
-- ----------------------------
DROP TABLE IF EXISTS `tp_auth_group`;
CREATE TABLE `tp_auth_group` (
    `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
    `title` char(100) NOT NULL DEFAULT '',
    `status` tinyint(1) NOT NULL DEFAULT '1',
    `rules` char(80) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- tp_auth_group_access 用户-用户组关联表
-- uid: 用户ID， group_id: 用户组ID
-- ----------------------------
DROP TABLE IF EXISTS `tp_auth_group_access`;
CREATE TABLE `tp_auth_group_access` (
    `uid` mediumint(8) unsigned NOT NULL,
    `group_id` mediumint(8) unsigned NOT NULL,
    UNIQUE KEY `uid_group_id` (`uid`,`group_id`),
    KEY `uid` (`uid`),
    KEY `group_id` (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
*/

/**
 * 权限认证核心类
 */
class Auth
{
    // ====================== 默认配置 ======================
    /**
     * 默认配置项
     * @var array
     * @config
     *   auth_on: 认证开关，true-开启，false-关闭
     *   auth_type: 认证方式，1-实时认证，2-登录认证（结果存入session）
     *   auth_group: 用户组表名（不含表前缀）
     *   auth_group_access: 用户-用户组关联表名
     *   auth_rule: 权限规则表名
     *   auth_user: 用户信息表名
     */
    protected $_config = array(
        'auth_on' => true,                      // 认证开关
        'auth_type' => 1,                      // 认证方式：1实时认证；2登录认证（结果存入session）
        'auth_group' => 'auth_group',          // 用户组数据表名（不含前缀）
        'auth_group_access' => 'auth_group_access', // 用户-用户组关系表名（不含前缀）
        'auth_rule' => 'auth_rule',            // 权限规则表名（不含前缀）
        'auth_user' => 'admin'                 // 用户信息表名（不含前缀）
    );

    /**
     * 实例配置名称（对应配置文件中的键名，用于多套权限配置）
     * @var string
     */
    protected $configName = '';

    /**
     * 构造函数
     *
     * 支持多套独立权限配置，通过配置文件中的不同键名来区分
     *
     * 配置示例（config/auth.php）：
     * return [
     *     'admin' => [
     *         'auth_on' => true,
     *         'auth_type' => 1,
     *         // ... 其他配置
     *     ],
     *     'subsystem' => [
     *         'auth_on' => true,
     *         'auth_type' => 2,
     *         // ... 其他配置
     *     ]
     * ];
     *
     * @param string $configName 配置名称（对应配置文件中的键名），为空则使用默认配置
     */
    public function __construct($configName = '')
    {
        $this->configName = $configName;
        $this->loadConfig();
    }

    /**
     * 加载并合并配置
     *
     * 加载顺序：
     * 1. 数据库表前缀自动添加到表名前面
     * 2. 如果指定了 configName，从 config('auth.xxx') 读取并合并
     * 3. 兼容旧版配置方式 config('auth.auth_config')
     *
     * @return void
     */
    protected function loadConfig()
    {
        $prefix = config('database.prefix');

        // 如果有指定配置名称，则使用对应的配置
        if (!empty($this->configName)) {
            $customConfig = config('auth.' . $this->configName);
            if ($customConfig && is_array($customConfig)) {
                $this->_config = array_merge($this->_config, $customConfig);
            }
        } else {
            // 兼容原有配置方式
            if (config('auth.auth_config')) {
                $this->_config = array_merge($this->_config, config('auth.auth_config'));
            }
        }

        // 设置完整的表名（自动添加表前缀）
        $this->_config['auth_group'] = $prefix . $this->_config['auth_group'];
        $this->_config['auth_rule'] = $prefix . $this->_config['auth_rule'];
        $this->_config['auth_user'] = $prefix . $this->_config['auth_user'];
        $this->_config['auth_group_access'] = $prefix . $this->_config['auth_group_access'];
    }

    /**
     * 获取当前实例的完整配置
     *
     * @return array 配置数组
     */
    public function getConfig()
    {
        return $this->_config;
    }

    /**
     * 检查用户是否拥有指定权限
     *
     * 这是权限认证的核心方法，支持单规则、多规则，以及规则间的逻辑关系。
     *
     * 使用示例：
     * ```php
     * $auth = new Auth();
     *
     * // 检查单个权限
     * if ($auth->check('user/edit', 100)) {
     *     echo '有权限';
     * }
     *
     * // 检查多个权限（OR关系，满足任一即可）
     * if ($auth->check('user/edit,user/delete', 100, 1, 'url', 'or')) {
     *     echo '有编辑或删除权限';
     * }
     *
     * // 检查多个权限（AND关系，必须全部满足）
     * if ($auth->check('user/edit,user/delete', 100, 1, 'url', 'and')) {
     *     echo '同时拥有编辑和删除权限';
     * }
     *
     * // 使用数组形式传递规则列表
     * $rules = ['user/edit', 'user/delete'];
     * if ($auth->check($rules, 100)) {
     *     echo '有权限';
     * }
     * ```
     *
     * @param string|array $name     需要验证的规则列表
     *                                - 字符串：单条规则，如 'user/edit'
     *                                - 逗号分隔字符串：多条规则，如 'user/edit,user/delete'
     *                                - 数组：规则数组，如 ['user/edit', 'user/delete']
     * @param int $uid                用户ID，对应 auth_user 表的主键
     * @param int $type               规则类型，对应 auth_rule 表的 type 字段，默认为1
     * @param string $mode           匹配模式，'url' 为URL参数模式（会解析规则中的URL参数进行匹配）
     * @param string $relation       规则间关系：'or' - 满足任一规则即通过，'and' - 需满足所有规则才能通过
     * @return boolean               通过验证返回 true，失败返回 false
     */
    public function check($name, $uid, $type = 1, $mode = 'url', $relation = 'or')
    {
        if (!$this->_config['auth_on'])
            return true;
        $authList = $this->getAuthList($uid, $type); //获取用户需要验证的所有有效规则列表
        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = array($name);
            }
        }
        $list = array(); //保存验证通过的规则名
        if ($mode == 'url') {
            $REQUEST = unserialize(strtolower(serialize($_REQUEST)));
        }
        foreach ($authList as $auth) {
            $query = preg_replace('/^.+\?/U', '', $auth);
            if ($mode == 'url' && $query != $auth) {
                parse_str($query, $param); //解析规则中的param
                $intersect = array_intersect_assoc($REQUEST, $param);
                $auth = preg_replace('/\?.*$/U', '', $auth);
                if (in_array($auth, $name) && $intersect == $param) {  //如果节点相符且url参数满足
                    $list[] = $auth;
                }
            } else if (in_array($auth, $name)) {
                $list[] = $auth;
            }
        }
        if ($relation == 'or' and !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ($relation == 'and' and empty($diff)) {
            return true;
        }
        return false;
    }

    /**
     * 获取用户所属的所有用户组
     *
     * 返回用户关联的所有启用状态（status=1）的用户组信息。
     * 结果会自动缓存，同一用户多次调用不会重复查询数据库。
     *
     * @param int $uid 用户ID
     * @return array 用户组数组，每个元素包含：
     *               - uid: 用户ID
     *               - group_id: 用户组ID
     *               - title: 用户组名称
     *               - rules: 该用户组拥有的规则ID列表（逗号分隔）
     */
    public function getGroups($uid)
    {
        static $groups = array();
        if (isset($groups[$uid]))
            return $groups[$uid];
        $user_groups = \think\facade\Db::table($this->_config['auth_group_access'])
            ->alias('a')
            ->where('a.uid', $uid)->where('g.status', '1')
            ->join($this->_config['auth_group'] . ' g', 'a.group_id=g.id')
            ->field('uid,group_id,title,rules')
            ->select();
        $groups[$uid] = $user_groups ?: array();
        return $groups[$uid];
    }

    /**
     * 获取用户的所有有效权限规则列表
     *
     * 处理流程：
     * 1. 获取用户所属的所有用户组
     * 2. 合并所有用户组的规则ID，去重
     * 3. 查询这些规则的具体信息（name和condition）
     * 4. 对每条规则进行验证：
     *    - 无condition：直接通过
     *    - 有condition：解析表达式，从用户表中获取对应字段进行验证
     * 5. 返回通过验证的规则名称列表
     *
     * @param int $uid   用户ID
     * @param int $type  规则类型（对应 auth_rule.type）
     * @return array     通过验证的规则名称列表
     */
    protected function getAuthList($uid, $type)
    {
        static $_authList = array(); //保存用户验证通过的权限列表
        $t = implode(',', (array)$type);
        if (isset($_authList[$uid . $t])) {
            return $_authList[$uid . $t];
        }
        if ($this->_config['auth_type'] == 2 && isset($_SESSION['_auth_list_' . $uid . $t])) {
            return $_SESSION['_auth_list_' . $uid . $t];
        }

        //读取用户所属用户组
        $groups = $this->getGroups($uid);
        $ids = array();//保存用户所属用户组设置的所有权限规则id
        foreach ($groups as $g) {
            $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
        }
        $ids = array_unique($ids);
        if (empty($ids)) {
            $_authList[$uid . $t] = array();
            return array();
        }
        $map[] = ['id', 'in', $ids];
        $map[] = ['type', '=', $type];
        $map[] = ['status', '=', 1];
        //读取用户组所有权限规则
        $rules = \think\facade\Db::table($this->_config['auth_rule'])->where($map)->field('condition,name')->select();

        //循环规则，判断结果。
        $authList = array();   //
        foreach ($rules as $rule) {
            if (!empty($rule['condition'])) { //根据condition进行验证
                $user = $this->getUserInfo($uid);//获取用户信息,一维数组

                $command = preg_replace('/\{(\w*?)\}/', '$user[\'\\1\']', $rule['condition']);
                //dump($command);//debug
                @(eval('$condition=(' . $command . ');'));
                if ($condition) {
                    $authList[] = strtolower($rule['name']);
                }
            } else {
                //只要存在就记录
                $authList[] = strtolower($rule['name']);
            }
        }
        $_authList[$uid . $t] = $authList;
        if ($this->_config['auth_type'] == 2) {
            //规则列表结果保存到session
            $_SESSION['_auth_list_' . $uid . $t] = $authList;
        }
        return array_unique($authList);
    }

    /**
     * 获取用户信息（用于验证条件表达式）
     *
     * 根据用户ID从 auth_user 表中查询用户数据。
     * 结果会自动缓存，同一用户多次调用不会重复查询数据库。
     *
     * 条件表达式示例：
     * - {score} > 60        ：用户score字段大于60
     * - {level} == 5        ：用户level字段等于5
     * - {score} > 60 and {score} < 100
     * - {dept_id} == 1 or {dept_id} == 2
     *
     * @param int $uid 用户ID
     * @return array   用户信息数组（键为字段名，值为字段值）
     */
    protected function getUserInfo($uid)
    {
        static $userinfo = array();
        if (!isset($userinfo[$uid])) {
            $userinfo[$uid] = \think\facade\Db::table($this->_config['auth_user'])->where('id', $uid)->find();
        }
        return $userinfo[$uid];
    }
}