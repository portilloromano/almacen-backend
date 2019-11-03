<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use App\Entity\Category;
use App\Entity\Product;
use App\Services\JwtAuth;

/**
 * @Route("/category")
 */
class CategoryController extends AbstractController
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
            $categoryRepo = $this->getDoctrine()->getRepository(Category::class);

            $category = $categoryRepo->findAll();
            $data = [
                'status' => 'success',
                'code' => 200,
                'category' => $category
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

            $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
            $category = $categoryRepo->findOneBy([
                'id' => $id
            ]);

            if ($category != null) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'category' => $category
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => '400',
                    'message' => 'No se encontró la categoría.',
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
            'message' => 'La categoría no se ha podido crear.'
        ];

        if ($authCheck['auth']) {
            $json = $request->get('json', null);
            $params = json_decode($json);
            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $description = (!empty($params->description)) ? $params->description : null;

                if (!empty($name) && !empty($description)) {
                    $category = new Category();
                    $category->setName($name);
                    $category->setDescription($description);
                    $category->setCreatedAt(new \DateTime('now'));

                    $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
                    $issetCategory = $categoryRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetCategory) == 0) {
                        $em = $this->getDoctrine()->getManager();
                        $em->persist($category);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'message' => 'La categoria se ha creado correctamente.',
                            'category' => $category
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'La categoría ya existe.'
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
            'message' => 'No se pudo actualizar la categoría.'
        ];

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();
            $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
            
            $json = $request->get('json', null);
            $params = json_decode($json);

            $category = $categoryRepo->findOneBy([
                'id' => $params->id
            ]);

            if ($category != null) {
                $categoryName = $category->getName();

                $name = (!empty($params->name)) ? $params->name : null;
                $description = (!empty($params->description)) ? $params->description : null;

                if (!empty($name) && !empty($description)) {
                    $category->setName($name);
                    $category->setDescription($description);
                    $category->setUpdatedAt(new \DateTime('now'));

                    $issetCategory = $categoryRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetCategory) == 0 || $categoryName == $name) {
                        $em->persist($category);
                        $em->flush();

                        $data = [
                            'status' => 'success',
                            'code' => '200',
                            'message' => 'La categoría ha sido actualizado.',
                            'category' => $category
                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => '400',
                            'message' => 'La categoría ya existe.'
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
            'message' => 'No se pudo eliminar la categoría.'
        ];

        if ($authCheck['auth']) {
            $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
            $category = $categoryRepo->findOneBy([
                'id' => $id
            ]);
            if ($category != null) {
                $productRepo = $this->getDoctrine()->getRepository(Product::class);

                $product = $productRepo->findOneBy([
                    'category_id' => $id
                ]);

                if ($product == null) {
                    $em = $this->getDoctrine()->getManager();
                    $em->remove($category);
                    $em->flush();

                    $data = [
                        'status' => 'success',
                        'code' => '200',
                        'message' => 'La categoría ha sido eliminado.',
                        'category' => $category
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => '200',
                        'message' => 'No se pudo eliminar la categoría porque tiene algún producto asociado.'
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
