<?php


class corutineReturnValue {
    protected $value;
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function getValue() {
        return $this->value;
    }
}

class coProxyValue {
    
    public function __construct($value) {
        $this->value = $value;
    }
    
    public function setType($type) {
        $this->type = $type;
        return $this;
    }
    
    public function getValue() {
        return $this->value;
    }
}

?>
