<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\HttpFoundation\JsonResponse;

use App\Entity\User;
use App\Services\JwtAuth;

/**
 * @Route("/user") 
 */
class UserController extends AbstractController
{
    private function resJSON($data)
    {
        $json = $this->get('serializer')->serialize($data, 'json');
        $response = new Response();
        $response->setContent($json);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route("/", methods={"GET"})
     */
    public function listAction(Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        if ($authCheck['auth'] && $authCheck['roles'] == 'ROLE_ADMIN') {
            $userRepo = $this->getDoctrine()->getRepository(User::class);

            $user = $userRepo->findAll();
            $data = [
                'status' => 'success',
                'code' => 200,
                'user' => $user
            ];
        } else {
            $data = [
                'status' => 'error',
                'code' => 200,
                'message' => 'No posee permisos para esta transacción.'
            ];
        }

        return $this->resJSON($data);
    }

    /**
     * @Route("/{id}", methods={"GET"})
     */
    public function getByIdAction(int $id, Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();

            $userRepo = $this->getDoctrine()->getRepository(User::class);
            $user = $userRepo->findOneBy([
                'id' => $id
            ]);

            if ($user != null) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'user' => $user
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => '400',
                    'message' => 'No se encontró el usuario.',
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 200,
                'message' => 'No posee permisos para esta transacción.'
            ];
        }

        return $this->resJSON($data);
    }

    /**
     * @Route("/", methods={"POST"})
     */
    public function createAction(Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        if ($authCheck['auth'] && $authCheck['roles'] == 'ROLE_ADMIN') {

            $json = $request->get('json', null);
            $params = json_decode($json);

            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'El usuario no se ha podido crear.'
            ];

            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;
                $password = (!empty($params->password)) ? $params->password : null;

                $validator = Validation::createValidator();
                $validateEmail = $validator->validate($email, [
                    new Email()
                ]);

                if (!empty($name) && !empty($surname) && !empty($email) && count($validateEmail) == 0 && !empty($password)) {
                    $user = new User();
                    $user->setName($name);
                    $user->setSurname($surname);
                    $user->setEmail($email);
                    $user->setPassword(hash('sha256', $password));
                    $user->setRoles('ROLE_USER');
                    $user->setCreatedAt(new \DateTime('now'));

                    $userRepo = $this->getDoctrine()->getRepository(User::class);
                    $issetUser = $userRepo->findBy(array(
                        'email' => $email
                    ));

                    if (count($issetUser) == 0) {
                        $em = $this->getDoctrine()->getManager();
                        $em->persist($user);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El usuario se ha creado correctamente.',
                            'user' => $user
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'El usuario ya existe.'
                        ];
                    }
                }
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 200,
                'message' => 'No posee permisos para esta transacción.'
            ];
        }

        return $this->resJSON($data);
    }

    /**
     * @Route("/login", methods={"POST"})
     */
    public function loginAction(Request $request, JwtAuth $jwtAuth)
    {
        $json = $request->get('json', null);
        $params = json_decode($json);

        $data = [
            'status' => 'error',
            'code' => 200,
            'message' => 'El usuario no se ha podido identificar.'
        ];

        if ($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;

            $validator = Validation::createValidator();
            $validateEmail = $validator->validate($email, [
                new Email()
            ]);

            if (!empty($email) && count($validateEmail) == 0 && !empty($password)) {
                $pwd = hash('sha256', $password);

                if ($gettoken) {
                    $signup = $jwtAuth->signup($email, $pwd, $gettoken);
                } else {
                    $signup = $jwtAuth->signup($email, $pwd);
                }

                return new JsonResponse($signup);
            }
        }

        return $this->resJSON($data);
    }

    /**
     * @Route("/", methods={"PUT"})
     */
    public function editAction(Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);
        $identity = $jwtAuth->checkToken($token, true);

        $json = $request->get('json', null);
        $params = json_decode($json);

        $data = [
            'status' => 'error',
            'code' => '400',
            'message' => 'No se pudo actualizar el usuario.'
        ];

        if ($params->id != 1) {
            if (($authCheck['auth'] && $identity->sub == $params->id) || $authCheck['roles'] == 'ROLE_ADMIN') {
                $em = $this->getDoctrine()->getManager();

                $userRepo = $this->getDoctrine()->getRepository(User::class);

                $user = $userRepo->findOneBy([
                    'id' => $params->id
                ]);

                if ($json != null) {
                    $userEmail = $user->getEmail();

                    $name = (!empty($params->name)) ? $params->name : null;
                    $surname = (!empty($params->surname)) ? $params->surname : null;
                    $email = (!empty($params->email)) ? $params->email : null;
                    $password = (!empty($params->password)) ? $params->password : null;

                    $validator = Validation::createValidator();
                    $validateEmail = $validator->validate($email, [
                        new Email()
                    ]);

                    if (!empty($name) && !empty($surname) && !empty($email) && count($validateEmail) == 0) {
                        $user->setName($name);
                        $user->setSurname($surname);
                        $user->setEmail($email);
                        if ($authCheck['roles'] == 'ROLE_ADMIN') $user->setRoles($params->roles);
                        if ($authCheck['roles'] == 'ROLE_ADMIN') $user->setActive($params->active);
                        if ($password != null) $user->setPassword(hash('sha256', $password));
                        $user->setUpdatedAt(new \DateTime('now'));

                        $issetUser = $userRepo->findBy(array(
                            'email' => $email
                        ));

                        if (count($issetUser) == 0 || $userEmail == $email) {
                            $em->persist($user);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => '200',
                                'message' => 'El usuario ha sido actualizado.',
                                'user' => $user
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => '400',
                                'message' => 'El email ya existe.'
                            ];
                        }
                    }
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'No posee permisos para esta transacción.'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => '200',
                'message' => 'El usuario principal no puede ser modificado en esta demo.'
            ];
        }

        return $this->resJSON($data);
    }
}
