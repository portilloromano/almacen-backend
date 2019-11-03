<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;

use App\Entity\Provider;
use App\Entity\Product;
use App\Services\JwtAuth;

/**
 * @Route("/provider")
 */
class ProviderController extends AbstractController
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

        if ($authCheck['auth']) {
            $providerRepo = $this->getDoctrine()->getRepository(Provider::class);

            $provider = $providerRepo->findAll();
            $data = [
                'status' => 'success',
                'code' => 200,
                'provider' => $provider
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

            $providerRepo = $this->getDoctrine()->getRepository(Provider::class);
            $provider = $providerRepo->findOneBy([
                'id' => $id
            ]);

            if ($provider != null) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'provider' => $provider
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => '400',
                    'message' => 'No se encontró el proveedor.',
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

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El proveedor no se ha podido crear.'
        ];

        if ($authCheck['auth']) {
            $json = $request->get('json', null);
            $params = json_decode($json);
            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $email = (!empty($params->email)) ? $params->email : null;
                $phone = (!empty($params->phone)) ? $params->phone : null;
                $address = (!empty($params->address)) ? $params->address : null;

                $validator = Validation::createValidator();
                $validateEmail = $validator->validate($email, [
                    new Email()
                ]);

                if (!empty($name) && !empty($email) && count($validateEmail) == 0) {
                    $provider = new Provider();
                    $provider->setName($name);
                    $provider->setEmail($email);
                    $provider->setPhone($phone);
                    $provider->setAddress($address);
                    $provider->setCreatedAt(new \DateTime('now'));

                    $providerRepo = $this->getDoctrine()->getRepository(Provider::class);
                    $issetProvider = $providerRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetProvider) == 0) {
                        $em = $this->getDoctrine()->getManager();
                        $em->persist($provider);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'El proveedor se ha creado correctamente.',
                            'provider' => $provider
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'El proveedor ya existe.'
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
     * @Route("/", methods={"PUT"})
     */
    public function editAction(Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => '400',
            'message' => 'No se pudo actualizar el proveedor.'
        ];

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();
            $providerRepo = $this->getDoctrine()->getRepository(Provider::class);

            $json = $request->get('json', null);
            $params = json_decode($json);

            $provider = $providerRepo->findOneBy([
                'id' => $params->id
            ]);

            if ($provider != null) {
                $providerName = $provider->getName();

                $name = (!empty($params->name)) ? $params->name : null;
                $email = (!empty($params->email)) ? $params->email : null;
                $phone = (!empty($params->phone)) ? $params->phone : null;
                $address = (!empty($params->address)) ? $params->address : null;

                $validator = Validation::createValidator();
                $validateEmail = $validator->validate($email, [
                    new Email()
                ]);

                if (!empty($name) && !empty($email) && count($validateEmail) == 0) {
                    $provider->setName($name);
                    $provider->setEmail($email);
                    $provider->setPhone($phone);
                    $provider->setAddress($address);
                    $provider->setUpdatedAt(new \DateTime('now'));

                    $issetProvider = $providerRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetProvider) == 0 || $providerName == $name) {
                        $em->persist($provider);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => '200',
                            'message' => 'El proveedor ha sido actualizado.',
                            'provider' => $provider
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => '400',
                            'message' => 'El proveedor ya existe.'
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
     * @Route("/{id}", methods={"DELETE"})
     */
    public function deleteAction(int $id, Request $request, JwtAuth $jwtAuth)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        $data = [
            'status' => 'error',
            'code' => '400',
            'message' => 'No se pudo eliminar el proveedor.'
        ];

        if ($authCheck['auth']) {
            $providerRepo = $this->getDoctrine()->getRepository(Provider::class);
            $provider = $providerRepo->findOneBy([
                'id' => $id
            ]);
            if ($provider != null) {
                $productRepo = $this->getDoctrine()->getRepository(Product::class);

                $product = $productRepo->findOneBy([
                    'provider_id' => $id
                ]);

                if ($product == null) {
                    $em = $this->getDoctrine()->getManager();
                    $em->remove($provider);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => '200',
                        'message' => 'El proveedor ha sido eliminado.',
                        'provider' => $provider
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => '200',
                        'message' => 'No se pudo eliminar el proveedor porque tiene algún producto asociado.'
                    ];
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
}
