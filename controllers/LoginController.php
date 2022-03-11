<?php

namespace Controllers;

use MVC\Router;
use Classes\Email;
use Model\Usuario;

class LoginController {
    public static function login(Router $router) {
        $alertas = [];

        $auth = new Usuario;

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);

            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // Comprobar que exista el usuario
                $usuario = Usuario::where('email', $auth->email);

                if($usuario) {

                    if($usuario->comprobarPasswordAndVerificado($auth->password)){
                        // Autenticar el usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        // Redireccionamiento

                        if($usuario->admin === "1") {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cita');
                        }
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login' , [
            'alertas' => $alertas,
            'auth' => $auth
        ]);
    }
    public static function logout() {
        // echo "Desde Logout";
        session_start();
        // debuguear($_SESSION);

        $_SESSION = [];
        // debuguear($_SESSION);
        header('Location: /');
    }
    public static function olvide(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            // debuguear($auth);

            if(empty($alertas)) {
                $usuario = Usuario::where('email', $auth->email);
                // debuguear($usuario);

                if($usuario && $usuario->confirmado === "1") {
                    // debuguear('Si Existe y esta confirmado');
                    // Generar un Token
                    $usuario->crearToken();
                    $usuario->guardar();

                    // debuguear($usuario);

                    //Enviar el email
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    $email->enviarInstrucciones();

                    // Alerta de éxito
                    Usuario::setAlerta('exito','Revisa tu email');
                    // $alertas = Usuario::getAlertas();


                } else {
                    // debuguear('No Existe o no confirmado');
                    Usuario::setAlerta('error', 'El Usuario no existe o no esta confirmado');
                    // $alertas = Usuario::getAlertas();
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }
    public static function recuperar(Router $router ) {
        // echo "Desde Recuperar";
        $alertas = [];
        $error = false;

        $token = s($_GET['token']);
        // debuguear($token);

        // Buscar usuario por su token
        $usuario = Usuario::where('token', $token);

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token No Válido');
            $error = true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
            // Leer el nuevo password y guardarlo
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();

            // debuguear($password);

            if(empty($alertas)) {
                $usuario->password = null;
                // debuguear($password);
                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: /');
                }

                // debuguear($usuario);
            }
        }
        // debuguear($usuario);
        $alertas = Usuario::getAlertas();
        $router->render('auth/recuperar-password', [
            'alertas' => $alertas,
            'error' => $error
        ]);
    }
    public static function crear(Router $router) {
        $usuario = new Usuario;

        // Alertas vacias
        $alertas = [];
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validaNuevaCuenta();

            // Revisar que alerta este vacio
            if(empty($alertas)){

                // Verificar que el usuario no este registrado
                $resultado = $usuario->existeUsuario();

                if($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                    //Hashear el Password
                    $usuario->hashPassword();

                    // Generar un Token único
                    $usuario->crearToken();

                    // Enviar el Email
                    $email = new Email($usuario->nombre, $usuario->email, $usuario->token);
                    $email->enviarConfirmacion();

                    // Crear el usuario
                    $resultado = $usuario->guardar();
                    if($resultado) {
                        // echo "Guardado Correctamente";
                        header('Location: /mensaje');
                    }
                    
                    // debuguear($usuario);
                }
            }
        }

        $router->render('auth/crear-cuenta', [ 
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function mensaje(Router $router) {

        $router->render('auth/mensaje');
    }
    public static function confirmar(Router $router) {

        $alertas = [];
        $token = s($_GET['token']);
        $usuario = Usuario::where('token', $token);

        // debuguear($token);
        // debuguear($usuario);

        if(empty($usuario)) {
            // Mostrar mensaje de error
            // echo "Token no válido";
            Usuario::setAlerta('error', 'Token No Válido');
        }else {
            // Modificar a usuario confirmado
            // echo "Token valído, confirmando usuario...";
            // debuguear($usuario);

            $usuario->confirmado = "1";
            // $usuario->token = null;
            $usuario->token = '';
            // debuguear($usuario);
            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta Comprobada Correctamente');
        }

        // Obtener alertas
        $alertas = Usuario::getAlertas();

        // Renderizar la vista
        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }
}