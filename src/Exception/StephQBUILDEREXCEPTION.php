<?php
/**
 * Created by PhpStorm.
 * User: Phanou
 * Date: 30/04/2019
 * Time: 14:21
 */
namespace steph\db_query\Exception;
class StephQBUILDEREXCEPTION extends \Exception
{
    /**
     * @var \Exception $e
     */
    public $e;

    public function Error($error, $message = null): \Exception
    {
        if ($message === null && empty($message)) {
            switch ($error) {
                case 0:
                    $this->e = new \Exception('must be give an sql a empty string given',1);
                    break;
                case 1:
                    $this->e = new \Exception('cannot use `from` outside query',1);
                    break;
                case 2:
                    $this->e = new \Exception('cannot use INSERT on sub-query',1);
                    break;
                case 3:
                    $this->e = new \Exception('cannot use this req on sub-query ',1);
                    break;
                case 4:
                    $this->e = new \Exception('must have start a where condition to use sub-query ',1);
                    break;
                default:
                    break;

            }
        } else {
            switch ($error) {
                case 1:
                case 2:
                case 3:
                case 4:
                case 0:
                    $this->e = new \Exception($message,1);
                    break;
                default:
                    break;

            }
        }
        if (!isset($this->e)) throw new \Exception('unknow error code');
        return $this->e;
    }

    public function showErr()
    {
        return $this->e->getMessage();
    }
}