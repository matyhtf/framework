<?php
namespace SPF\Html;

class Table
{
    public $rows;
    public $cols;
    public $data;
    public $head;
    public $attrs;

    public $html;

    public function __construct($data = null, $attrs = null)
    {
        if ($data) {
            $this->data = $data;
        }
        if ($attrs) {
            $this->attrs = $attrs;
        }
    }

    private function parseAttrs()
    {
        if (empty($this->attrs)) {
            return false;
        }
        $str = '';
        foreach ($this->attrs as $k => $at) {
            $str .= $k . '="' . $at . '"';
        }
        return $str;
    }

    private function parseBoby()
    {
        $str = '';
        foreach ($this->data as $row) {
            $str .= "<tr>\n";
            foreach ($row as $cell) {
                $str .= '<td>' . $cell . '</td>';
            }
            $str .= "</tr>\n";
        }
        return $str;
    }

    public function html()
    {
        $html = '<table ' . $this->parseAttrs() . '>';
        $html .= $this->parseBoby();
        $html .= '</table>';
        return $html;
    }
}
