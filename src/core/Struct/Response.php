<?php
namespace SPF\Struct;

use SPF;

class Response extends Map
{
    /**
     * @var int
     */
    protected $code = 0;

    /**
     * @var string
     */
    protected $msg = "";

    /**
     * @param int $code
     * @param string $msg
     * @param array $data
     */
    public function __construct(int $code = 0, string $msg = "success", array $data = [])
    {
        $this->code = $code;
        $this->msg = $msg;

        $this->sets($data);
    }

    /**
     * @param int $code
     * @param string $msg
     *
     * @return self
     */
    public function error(int $code, string $msg = '')
    {
        $this->code = $code;

        $msg && $this->msg = $msg;

        return $this;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->msg;
    }

    /**
     * @return string
     */
    public function toJson()
    {
        $data = $this->toArray();
        $data = $this->toArrayRecusive($data);

        $jsonArr = [
            'code' => $this->getCode(),
            'msg' => $this->getMessage(),
            'data' => $data,
        ];

        return json_encode($jsonArr);
    }

    /**
     * Recusive to array
     *
     * @param array $data
     *
     * @return array
     */
    protected function toArrayRecusive($data)
    {
        foreach ($data as &$item) {
            if (is_object($item) && method_exists($item, 'toArray')) {
                $item = $item->toArray();
            }
            if (is_array($item)) {
                $item = $this->toArrayRecusive($item);
            }
        }
        unset($item);

        return $data;
    }
}
