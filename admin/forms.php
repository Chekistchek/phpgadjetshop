<?php
namespace admin\forms;

use \core\forms\Form;
use \core\urls\Url;
use \admin\models\Auth_User;


class AdminForm extends Form
{
    public function __construct()
    {
        $this->obj = new Auth_User();

        $this->obj_fields = []; // нужные поля для залогирвания
        $this->obj_fields['username'] = $this->obj->mandatory_fields['username'];
        $this->obj_fields['password'] = $this->obj->mandatory_fields['password'];

        parent::__construct();
    }

    protected function upload_data_fill()
    {               
        $users = $this->obj->get_objects()->make_query();
        $logins = []; // логины из бд
        foreach ($users as $user) { // проверяем существует ли такой
            $logins[] = $user['username'];
            if ($user['username'] === $this->POST['username']) {
                $user = $user;
                break;
            }
            unset($user);
        }
        $password = SECRET_KEY . $this->POST['password'];

        if (!isset($user) || !password_verify($password, $user['password'])) {
            $this->log = '<p class="errornote">Неправильный логин или пароль.</p>';
        } else {
            $this->log = '<p class="success">Вы вошли в систему.</p>';
            $_SESSION['login'] = $user['username'];

            global $urlpatterns;
            $url = new Url($urlpatterns);
            $url = $url->url('admin');

            header("Location: ". $url);
            exit();
        }
    }
}

class RegisterForm extends Form
{
    public function __construct()
    {
        $this->obj = new Auth_User();

        $this->obj_fields = $this->obj->mandatory_fields;
        $this->obj_fields['password']['repeat_password'] = true;

        parent::__construct();
    }

    protected function upload_data_fill()
    {
        $this->log = '';

        $objects = new Auth_User();
        $objects = $objects->get_objects()->filter('username', '=', $this->POST['username'])->make_query();
        if ($objects) $this->log .= '<p style="color:red">Такой логин уже существует.</p>';

        $objects = new Auth_User();
        $objects = $objects->get_objects()->filter('email', '=', $this->POST['email'])->make_query();
        if ($objects) $this->log .= '<p style="color:red">Такая электронная почта уже существует.</p>';

        if (!$this->log) { // если все ок
            $this->success_log = '<p class="success">Запись была успешно добавлена. Вы можете отредактировать ее еще раз ниже.</p>';
            $this->method_name = 'add_object';
            $this->method_params = [$this->POST];

            $method_name = $this->method_name;
            $method_params = $this->method_params;

            $this->obj->$method_name(...$method_params);
            $this->log = $this->success_log;

            global $urlpatterns;
            $url = new Url($urlpatterns);
            $url = $url->url('admin');

            header("Location: ". $url);
            exit();
        }
    }
}

class AddObjectForm extends Form
{
    public function __construct($obj)
    {
        $this->obj = $obj;
        $this->obj_fields = $this->obj->mandatory_fields;

        parent::__construct();
    }

    protected function upload_data_fill()
    {
        $this->success_log = '<p class="success">Запись была успешно добавлена. Вы можете отредактировать ее еще раз ниже.</p>';
        $this->method_name = 'add_object';
        $this->method_params = [$this->POST];
        
        parent::upload_data_fill();
    }
}

class EditObjectForm extends Form
{
    public function __construct($obj, $id)
    {
        $this->obj_id = $id;
        $this->obj = $obj;
        
        $this->obj_fields = $this->obj->mandatory_fields;
        $this->construct_fill();

        if (!$this->POST) { // если ничего не передано, устанавливаем стандартные значения из бд
            $this->POST = $this->obj->get_object($this->obj_id);
        }

        $this->generate_form_description(); 
    }

    protected function upload_data_fill()
    {
        $this->success_log = '<p class="success">Запись была успешно обновлена. Вы можете отредактировать ее еще раз ниже.</p>';
        $this->method_name = 'edit_object';
        $this->method_params = [$this->POST, $this->obj_id];
        parent::upload_data_fill();
    }
}