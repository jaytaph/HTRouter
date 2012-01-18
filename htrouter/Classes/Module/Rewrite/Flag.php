<?php

namespace HTRouter\Module\Rewrite;

class Flag {
    const TYPE_BEFORE       =  'B';
    const TYPE_CHAIN        =  'C';
    const TYPE_COOKIE       =  'CO';
    const TYPE_DISCARDPATH  =  'DPI';
    const TYPE_ENV          =  'E';
    const TYPE_FORBIDDEN    =  'F';
    const TYPE_GONE         =  'G';
    const TYPE_HANDLER      =  'H';
    const TYPE_LAST         =  'L';
    const TYPE_NEXT         =  'N';
    const TYPE_NOCASE       =  'NC';
    const TYPE_NOESCAPE     =  'NE';
    const TYPE_NOSUBREQS    =  'NS';
    const TYPE_PROXY        =  'P';
    const TYPE_PASSTHROUGH  =  'PT';
    const TYPE_QSA          =  'QSA';
    const TYPE_REDIRECT     =  'R';
    const TYPE_SKIP         =  'S';
    const TYPE_MIMETYPE     =  'T';
    const TYPE_ORNEXT       =  'OR';
    const TYPE_NOVARY       =  'NV';

    protected $_type;
    protected $_key = null;
    protected $_value = null;

    function __construct($type, $key = "", $value = "") {
        $this->_type = $type;
        $this->_key = $key;
        $this->_value = $value;
    }

    function __toString() {
        $ret = (string)$this->_type;
        if ($this->_key) $ret .= "=".$this->getKey();
        if ($this->_value) $ret .= ":".$this->getValue();
        return $ret;
    }

    /**
     * @return null|string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * @return null|string
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @return null|string
     */
    public function getValue()
    {
        return $this->_value;
    }


}
