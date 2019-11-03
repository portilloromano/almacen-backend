<?php
namespace App\Services;

use Firebase\JWT\JWT;
use App\Entity\User;
use PhpParser\Node\Stmt\TryCatch;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth{
    public $manager;
    private $secret;

    public function __construct($manager){
        $this->manager = $manager;
        $this->secret = '3VGeZ4P2uT8d6J3vXfKN8u5wGbuJMK4K4fUPBbTUgWLCnGkNzNeQ2NrGZwmVuBKLQW67bspsBmUTVCAmmpMD9Sfw3NLrJHdaTL8bewbJ3HDZUgTj8msq2thEb8JMbhvV7gV5gSQHk9LBHnLbXvRzk64LmAG7MSsbEVHgNxpKDzuk9mVJfW8WUscWF2w33j7mSb6Zx63xdTNKCGTnkyJ3jnKuJSMYcegVcuUed7GDBBRxUhVX5NkYJgfp5KPUUNLj';
    }

    public function signup($email, $password, $gettoken = null){
        $user = $this->manager->getRepository(User::class)->findOneBy([
            'email' => $email,
            'password' => $password
        ]);

        $signup = false;
        if(is_object($user)){
            $signup = true;
        }

        if($signup){
            $token = [
                'sub' => $user->getId(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'email' => $user->getEmail(),
                'iat' => time(),
                'exp' => time() + (7 * 24 * 60 * 60)
            ];

            $jwt = JWT::encode($token, $this->secret, 'HS256');
            if(!empty($gettoken)){
                $data = $jwt;
            }else{
                $decoded = JWT::decode($jwt, $this->secret, ['HS256']);
                $data = $decoded;
            }
        }else{
            $data = [
                'status' => 'error',
                'message' => 'Login incorrecto.'
            ];
        }
        return $data;
    }

    public function checkToken($jwt, $identity = false){
        try{
            $decoded = JWT::decode($jwt, $this->secret, ['HS256']);
        }catch(\UnexpectedValueException $e){
            $auth = false;
        }catch(\DomainException $e){
            $auth = false;
        }

        if(isset($decoded) && !empty($decoded) && is_object($decoded) && isset($decoded->sub)){
            $user = $this->manager->getRepository(User::class)->findOneBy([
                'id' => $decoded->sub
            ]);
            if($user->getActive()){
                $auth = true;
            }else{
                $auth = false;
            }
        }else{
            $auth = false;
        }

        if($identity != false){
            return $decoded;
        }else{
            if(isset($user)){
                return ['auth'=>$auth, 'roles'=>$user->getRoles()];
            }else{
                return ['auth'=>$auth];
            }

        }
    }


}