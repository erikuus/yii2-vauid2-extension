<?php
/**
 * VauUserIdentity class authenticates user based on VauID 2.0 protocol
 *
 * @link http://www.ra.ee/apps/vauid/
 * @link https://github.com/erikuus/yii2-vauid2-extension#readme
 * @author Erik Uus <erik.uus@gmail.com>
 * @version 1.0
 */

namespace rahvusarhiiv\vauid;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;
use rahvusarhiiv\vauid\VauAccessDeniedException;

class VauUserIdentity extends \yii\base\BaseObject implements \yii\web\IdentityInterface
{
    public $vauData = [];

    private $_user;

    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Authenticates VAU user
     * @param string $data the data posted back by VAU after successful login
     * @param array $options the authentication options
     * @param integer $requestLifetime the number of seconds VAU postback is valid
     * @throws Exception or VauAccessDeniedException if authentication fails
     */
    public function authenticate($data, $options = [], $requestLifetime = 60)
    {
        $vauUserData=$this->decodeVauUserData($data);

        $this->checkVauRequestTimestamp($vauUserData['timestamp'], $requestLifetime);
        $this->checkAccess($vauUserData, $options);

        if (ArrayHelper::getValue($options, 'dataMapping')) {
            $this->checkRequiredDataMapping($options);
            $user=$this->findUser($vauUserData, $options);
            if ($user === null) {
                if (ArrayHelper::getValue($options, 'dataMapping.create')) {
                    $user=$this->createUser($vauUserData, $options);
                } else {
                    throw new VauAccessDeniedException('Access denied because user not found and "create" not enabled!');
                }
            } elseif (ArrayHelper::getValue($options, 'dataMapping.update')) {
                $user=$this->updateUser($user, $vauUserData, $options);
            }
            $this->_user = $user;
        } else {
            Yii::$app->session->set('__data', $vauUserData);
            $this->_user = new static();
        }
    }

    /**
     * Decode JSON posted back by VAU after successful login
     * @param string $data the json encoded VAU user data
     * @return array VAU user data
     * @throws Exception if decoding fails
     */
    protected function decodeVauUserData($data)
    {
        $vauUserData=Json::decode($data);
        if (json_last_error() == JSON_ERROR_NONE) {
            return $vauUserData;
        } else {
            throw new Exception('Failed to decode json posted back by VAU!');
        }
    }

    /**
     * Check whether VAU request timestamp is valid
     * @param integer $vauRequestTimestamp the unix time when VAU postback was created
     * @param integer $requestLifetime the number of seconds VAU postback is valid
     * @throws Exception if VAU request timestamp is not valid
     */
    protected function checkVauRequestTimestamp($vauRequestTimestamp, $requestLifetime)
    {
        if ((time() - strtotime($vauRequestTimestamp)) > $requestLifetime) {
            throw new Exception('Request timestamp posted back by VAU is not valid!');
        }
    }

    /**
     * Check whether user can be authenticated by access rules
     * @param array $vauUserData the user data based on VauID 2.0 protocol
     * @param array $authOptions the authentication options
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccess($vauUserData, $authOptions)
    {
        $this->checkAccessBySafeloginRule(ArrayHelper::getValue($authOptions, 'accessRules.safelogin'), $vauUserData['safelogin']);
        $this->checkAccessBySafehostRule(ArrayHelper::getValue($authOptions, 'accessRules.safehost'), $vauUserData['safehost']);
        $this->checkAccessBySafeRule(ArrayHelper::getValue($authOptions, 'accessRules.safe'), $vauUserData['safelogin'], $vauUserData['safehost']);
        $this->checkAccessByEmployeeRule(ArrayHelper::getValue($authOptions, 'accessRules.employee'), $vauUserData['type']);

        $accessRulesRoles = ArrayHelper::getValue($authOptions, 'accessRules.roles', []);
        $vauUserDataRoles = ArrayHelper::getValue($vauUserData, 'roles', []);
        $this->checkAccessByRolesRule($accessRulesRoles, $vauUserDataRoles);
    }

    /**
     * Check whether user can be authenticated by safelogin access rules
     * @param boolean $accessRulesSafelogin the safelogin flag in access rules
     * @param boolean $vauUserDataSafelogin the safelogin flag in VAU postback
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccessBySafeloginRule($accessRulesSafelogin, $vauUserDataSafelogin)
    {
        if ($accessRulesSafelogin === true && $vauUserDataSafelogin !== true) {
            throw new VauAccessDeniedException('Access denied by safelogin rule!');
        }
    }

    /**
     * Check whether user can be authenticated by safehost access rules
     * @param boolean $accessRulesSafehost the safehost flag in access rules
     * @param boolean $vauUserDataSafehost the safehost flag in VAU postback
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccessBySafehostRule($accessRulesSafehost, $vauUserDataSafehost)
    {
        if ($accessRulesSafehost === true && $vauUserDataSafehost !== true) {
            throw new VauAccessDeniedException('Access denied by safehost rule!');
        }
    }

    /**
     * Check whether user can be authenticated by safe access rules
     * @param boolean $accessRulesSafe the safe flag in access rules
     * @param boolean $vauUserDataSafelogin the safelogin flag in VAU postback
     * @param boolean $vauUserDataSafehost the safehost flag in VAU postback
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccessBySafeRule($accessRulesSafe, $vauUserDataSafelogin, $vauUserDataSafehost)
    {
        if ($accessRulesSafe === true && $vauUserDataSafelogin !== true && $vauUserDataSafehost !== true) {
            throw new VauAccessDeniedException('Access denied by safe rule!');
        }
    }

    /**
     * Check whether user can be authenticated by employee access rules
     * @param boolean $accessRulesEmployee the access rule whether
     * @param integer $vauUserDataType the type of user in VAU
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccessByEmployeeRule($accessRulesEmployee, $vauUserDataType)
    {
        if ($accessRulesEmployee === true && $vauUserDataType != 1) {
            throw new VauAccessDeniedException('Access denied by employee rule!');
        }
    }

    /**
     * Check whether user can be authenticated by roles access rules
     * @param array $accessRulesRoles the list of role names in access rules
     * @param array $vauUserDataRoles the list of role names assigned to user in VAU
     * @throws VauAccessDeniedException if access is denied
     */
    protected function checkAccessByRolesRule($accessRulesRoles, $vauUserDataRoles)
    {
        if ($accessRulesRoles !== [] && array_intersect($accessRulesRoles, $vauUserDataRoles) === []) {
            throw new VauAccessDeniedException('Access denied by roles rule!');
        }
    }

    /**
     * Check whether required data mapping parameters are set
     * @param array $authOptions the authentication options
     * @throws Exception if data mapping is incomplete
     */
    protected function checkRequiredDataMapping($authOptions)
    {
        if (!ArrayHelper::getValue($authOptions, 'dataMapping.model') || !ArrayHelper::getValue($authOptions, 'dataMapping.id')) {
            throw new Exception('Model name and vauid have to be set in data mapping!');
        }
    }

    /**
     * Find user
     * @param array $vauUserData the user data based on VauID 2.0 protocol
     * @param array $authOptions the authentication options
     * @return ActiveRecord the user data | null
     */
    protected function findUser($vauUserData, $authOptions)
    {
        $modelName = ArrayHelper::getValue($options, 'dataMapping.model');

        return $modelName::findOne([
            ArrayHelper::getValue($options, 'dataMapping.id') => (int)$vauUserData['id']
        ]);
    }

    /**
     * Create new user
     * @param array $vauUserData the user data based on VauID 2.0 protocol
     * @param array $authOptions the authentication options
     * @return ActiveRecord the user data
     * @throws Exception if save fails
     */
    protected function createUser($vauUserData, $authOptions)
    {
        $modelName = ArrayHelper::getValue($authOptions, 'dataMapping.model');
        $scenario = ArrayHelper::getValue($authOptions, 'dataMapping.scenario');

        $user = $scenario ? new $modelName(['scenario' => $scenario]) : new $modelName();
        $user->{ArrayHelper::getValue($authOptions, 'dataMapping.id')} = $vauUserData['id'];

        foreach (ArrayHelper::getValue($authOptions, 'dataMapping.attributes') as $key => $attribute) {
            $user->{$attribute} = ArrayHelper::getValue($vauUserData, $key);
        }

        if (!$user->save()) {
            throw new Exception('Failed to save VAU user data into application database!');
        }

        return $user;
    }

    /**
     * Update user
     * @param CActiveRecord the user object
     * @param array $vauUserData the user data based on VauID 2.0 protocol
     * @param array $authOptions the authentication options
     * @return ActiveRecord the user data
     * @throws Exception if save fails
     */
    protected function updateUser($user, $vauUserData, $authOptions)
    {
        $scenario = ArrayHelper::getValue($authOptions, 'dataMapping.scenario');

        if ($scenario) {
            $user->scenario = $scenario;
        }

        foreach (ArrayHelper::getValue($authOptions, 'dataMapping.attributes') as $key => $attribute) {
            $user->{$attribute} = ArrayHelper::getValue($vauUserData, $key);
        }

        if (!$user->save()) {
            throw new Exception('Failed to save VAU user data into application database!');
        }

        return $user;
    }

    public function getId()
    {
        $data = Yii::$app->session->get('__data');
        return isset($data['id']) ? $data['id'] : null;
    }

    public static function findIdentity($id)
    {
        $selfInstance = new self();
        $data = Yii::$app->session->get('__data');
        return new static(['vauData' => $data]);
    }

    public function getAuthKey()
    {
    }

    public function validateAuthKey($authKey)
    {
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
    }
}
