<?php

class FormBuilder {

    protected $errors     = array();
    protected $inputs     = array();
    protected $attrs      = array();
    protected $validators = array();

    protected $html_before = '<div class="%s">';
    protected $html_after  = '</div>';

    protected $data     = array();

    protected $error_messages = array();

    protected $default_attrs = array(
        'method'            => 'post',
        'accept-charset'    => 'utf-8',
        'enctype'           => 'application/x-www-form-urlencoded'
    );

    function __construct($action='', $params=array()){

        if( !empty($action) && empty($params) && is_array($action)  ){
            $params = $action;
            $action = '';
        }

        if( !empty($action) ){
            $this->set_attr('action', $action);
        }

        $args = array_merge( $this->default_attrs, $params );
        foreach( $args as $attr_name => $attr_value ){
            $this->set_attr($attr_name, $attr_value);
        }

    }

    function set_error_messages($m){
        $this->error_messages = $m;
    }

    function attr($key){
        if( isset($this->attrs[$key]) ){
            return $this->attrs[$key];
        }
        return NULL;
    }

    function set_attr($key, $value){
        $this->attrs[$key]=$value;
        return $this;
    }

    function set_data($data){
        $this->data = $data;
    }

    function data(){
        return $this->data;
    }

    function render(){
        $html   = sprintf("<form %s>\n", $this->serialize_attrs($this->attrs) );
        foreach( array_keys($this->inputs) as $input_name ){
            $html .= $this->input_html( $input_name );
        }
        $html  .= "</form>";
        return $html;
    }

    function inputs(){
        return $this->inputs;
    }

    function input($name){
        return isset($this->inputs[$name]) ? $this->inputs[$name] : NULL;
    }

    function validate(){

        if( !$this->check_for_required_fields() ){
            return FALSE;
        }

        if( ! $this->check_for_confirmation_fields() ){
            return FALSE;
        }

        if( $this->check_for_custom_validators() ){
            return FALSE;
        }

        return TRUE;
    }

    function input_html($name){

        $input = isset($this->inputs[ $name ]) ? $this->inputs[ $name ] : NULL;

        if( empty($input) ){
            return '';
        }

        $html  = '';

        if( !empty($this->html_before) ){
            if( !isset($this->errors[ $name ]) ){
                $html .= $this->html_before . "\n";
            }else{
                $html .= sprintf($this->html_before, "field-with-errors " . join($this->errors[$name]) ) . "\n";
            }
        }

        if( $input['label'] != FALSE ){
            $html .= sprintf('<label for="%s">%s</label>', $name, $input['label']);
            $html .= "\n";
        }

        if( $input['type']=='textarea' ){

            $html .= sprintf(
                '<textarea %s>%s</textarea>',
                $this->serialize_attrs($input['attrs']),
                isset($this->data[$name]) ? $this->data[$name] : ''
            );

        }elseif( $input['type'] == 'select'  ){

            $html .= sprintf('<select %s>', $this->serialize_attrs($input['attrs']) );
            $html .= '</select>';

        }elseif( $input['type'] == 'radio' || $input['type'] == 'checkbox' ){

        }else{

            $html .= sprintf(
                '<input type="%s" %s %s />',
                 $input['type'],
                isset($this->data[$name]) ? 'value="' . $this->data[$name] .'"' : '',
                 $this->serialize_attrs($input['attrs'])
            );

        }

        if( isset($this->errors[ $name ]) ){
            $html .= sprintf(
                '<span class="error-message %s">%s</span>',
                join(' ', $this->errors[ $name ]),
                $this->error_message($name, $this->errors[ $name ][0])
            );
        }

        if( !empty($this->html_after) ){
            $html .= $this->html_after . "\n";
        }

        return $html;

    }

    function add_input($name, $type='', $params=array()){
        $input = array(
            'attrs' => array(
                'name'     => $name
            ),
            'type'         => (!empty($type) ? $type : 'text'),
            'before_html'  => '<div>',
            'after_html'   => '</div>',
            'required'     => FALSE,
            'confirm'      => FALSE,
            'label'        => FALSE
        );
        $not_attrs = array('before_html', 'after_html', 'label', 'required', 'label', 'confirm');
        foreach( $params as $attr => $value ){
            if( in_array($attr, $not_attrs) ){
                $input[ $attr ] = $value;
            }else{
                $input['attrs'][ $attr ] = $value;
            }
        }
        $this->inputs[$name] = $input;
        return $this;
    }

    function serialize_attrs($attrs=array()){
        $html_attrs = '';
        foreach( $attrs as $attr => $value ){
            if( is_array($value) ){
                $html_attrs .= sprintf('%s="%s" ', $attr, array_join(' ', $value) );
            }else{
                $html_attrs .= sprintf('%s="%s" ', $attr, $value);
            }
        }
        return $html_attrs;
    }

    function check_for_required_fields(){
        $has_errors = FALSE;
        foreach($this->inputs as $input){
            if( $input['required'] == FALSE ){
                continue;
            }
            $name = $input['attrs']['name'];
            if( !isset($this->data[ $name ]) || ($this->data[ $name ]=='') ){
                $this->add_error($name, 'required');
            }
            $has_errors = TRUE;
        }
        return $has_errors;
    }

    function check_for_confirmation_fields(){
        $has_errors = FALSE;
        foreach($this->inputs as $input){
            if( $input['confirm'] == FALSE ){
                continue;
            }
            $name          = $input['attrs']['name'];
            $confirm_field = $name . '_confirmation';
            if( !isset($this->data[ $confirm_field ]) || ($this->data[ $confirm_field ]=='') ){
                $this->add_error($confirm_field, $confirm_field);
            }
            $has_errors = TRUE;
        }
        return $has_errors;
    }

    function check_for_custom_validators(){
        return TRUE;
    }

    function add_error($field, $code){
        if( !isset($this->errors[ $field ]) ){
            $this->errors[ $field ] = array();
        }
        if( !in_array( $code, $this->errors[ $field ] ) ){
            $this->errors[ $field ][] = $code;
        }
    }

    function error_message($field, $type){
        $error = 'Campo inválido';
        if( isset($this->error_messages[$field]) ){
            if( isset($this->error_messages[$field][$type]) ){
                return $this->error_messages[$field][$type];
            }
        }
        return $error;
    }

}

function pr($data){
    echo "<pre>";
    print_r($data);
    echo "</pre>";
}
