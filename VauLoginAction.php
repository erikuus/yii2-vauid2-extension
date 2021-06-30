<?php
/**
 * VauLoginAction makes use of {@link VauSecurityManager} and {@link VauUserIdentity} to authenticate user based on VauID 2.0 protocol
 *
 * @link http://www.ra.ee/apps/vauid/
 * @link https://github.com/erikuus/yii2-vauid2-extension#readme
 * @author Erik Uus <erik.uus@gmail.com>
 * @version 1.0
 */

namespace rahvusarhiiv\vauid;

use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use rahvusarhiiv\vauid\VauUserIdentity;
use rahvusarhiiv\vauid\VauAccessDeniedException;

class VauLoginAction extends Action
{
    /**
     * @var string $securityManagerName the name of the vauid security manager {@link VauUserIdentity}.
     * Defaults to 'vauSecurityManager'.
     */
    public $securityManagerName = 'vauSecurityManager';
    /**
     * @var string $redirectUrl the url user will be redirected after successful login.
     * If empty, Yii::$app->user->returnUrl will be used.
     */
    public $redirectUrl;
    /**
     * @var array $authOptions the authentication options
     * @see VauUserIdentity::authenticate()
     */
    public $authOptions = [];
    /**
     * @var integer the number of seconds VAU postback is valid.
     */
    public $requestLifetime = 60;
    /**
     * @var boolean $enableLogging whether to log failed login requests
     */
    public $enableLogging = false;

    /**
     * Disables csrf validation to handle VAU POST request
     */
    public function init()
    {
        $this->controller->enableCsrfValidation = false;
    }

    /**
     * Logins user into application based on data posted by VAU after successful login
     */
    public function run()
    {
        if (!isset($_POST['postedData'])) {
            throw new BadRequestHttpException('Bad request. Please do not repeat this request again.');
        }

        if (Yii::$app->has($this->securityManagerName)) {
            $jsonData=Yii::$app->{$this->securityManagerName}->decrypt($_POST['postedData']);
        } else {
            throw new InvalidConfigException('The "VauSecurityManager" component have to be defined in configuration file.');
        }

        try {
            $identity=new VauUserIdentity();
            $identity->authenticate($jsonData, $this->authOptions, $this->requestLifetime);
            if (Yii::$app->user->login($identity->getUser())) {
                $this->controller->redirect($this->redirectUrl ? $this->redirectUrl : Yii::$app->user->returnUrl);
            } else {
                throw new Exception('Login failed.');
            }
        } catch (VauAccessDeniedException $e) {
            throw new ForbiddenHttpException('You do not have the proper credential to access this page.');
        } catch (Exception $e) {
            if ($this->enableLogging) {
                Yii::error($e->getMessage() . PHP_EOL . $jsonData);
            }
            throw new BadRequestHttpException('Bad request. Please do not repeat this request again.');
        }
    }
}
