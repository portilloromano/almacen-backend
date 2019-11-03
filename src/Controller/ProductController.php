<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Knp\Component\Pager\PaginatorInterface;

use App\Entity\Product;
use App\Entity\Provider;
use App\Entity\Category;
use App\Services\JwtAuth;

/**
 * @Route("/product")
 */
class ProductController extends AbstractController
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
    public function listAction(Request $request, JwtAuth $jwtAuth, PaginatorInterface $paginator)
    {
        $token = $request->headers->get('Authorization');

        $authCheck = $jwtAuth->checkToken($token);

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();

            $productRepro = $this->getDoctrine()->getRepository(Product::class);
            $query = $productRepro->findAll();

            $page = (int) $request->headers->get('page', 1);
            $items_per_page = 5;

            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

            $data = [
                'status' => 'success',
                'code' => 200,
                'total_items' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'products' => $pagination
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
     * @Route("/search", methods={"GET"})
     */
    public function listBySearchAction(Request $request, JwtAuth $jwtAuth, PaginatorInterface $paginator)
    {
        $token = $request->headers->get('Authorization');

        $search = $request->headers->get('search');
        $page = $request->headers->get('page');

        $authCheck = $jwtAuth->checkToken($token);

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();

            $search = (trim($search) == '') ? '%' : $search;
            $page = (trim($page) == '') ? 1 : (int)$page;

            $productRepro = $this->getDoctrine()->getRepository(Product::class);
            $sql = $productRepro->createQueryBuilder('p')
                ->where('p.name LIKE :search')
                ->orWhere('p.description LIKE :search')
                ->setParameter('search', '%'.$search.'%')
                ->getQuery();

            $query = $sql->getResult();

            $items_per_page = 5;

            $pagination = $paginator->paginate($query, $page, $items_per_page);
            $total = $pagination->getTotalItemCount();

            $data = [
                'status' => 'success',
                'code' => 200,
                'total_items' => $total,
                'page_actual' => $page,
                'items_per_page' => $items_per_page,
                'total_pages' => ceil($total / $items_per_page),
                'products' => $pagination
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

            $productRepo = $this->getDoctrine()->getRepository(Product::class);
            $product = $productRepo->findOneBy([
                'id' => $id
            ]);

            if ($product != null) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'product' => $product
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => '400',
                    'message' => 'No se encontró el producto.',
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
            'message' => 'El producto no se ha podido crear.'
        ];

        if ($authCheck['auth']) {
            $json = $request->get('json', null);
            $params = json_decode($json);

            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $category_id = (!empty($params->categoryId)) ? $params->categoryId : null;
                $provider_id = (!empty($params->providerId)) ? $params->providerId : null;

                if (!empty($name) && !empty($category_id) && !empty($provider_id)) {
                    $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
                    $category = $categoryRepo->findOneBy([
                        'id' => $category_id
                    ]);

                    $providerRepo = $this->getDoctrine()->getRepository(Provider::class);
                    $provider = $providerRepo->findOneBy([
                        'id' => $provider_id
                    ]);

                    $product = new Product();
                    $product->setName($name);
                    $product->setDescription($description);
                    $product->setCategory($category);
                    $product->setProvider($provider);
                    $product->setCreatedAt(new \DateTime('now'));

                    $productRepo = $this->getDoctrine()->getRepository(Product::class);
                    $issetProduct = $productRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetProduct) == 0) {
                        if ($category != null) {
                            if ($provider != null) {
                                $em = $this->getDoctrine()->getManager();
                                $em->persist($product);
                                $em->flush();

                                $data = [
                                    'status' => 'success',
                                    'code' => 200,
                                    'message' => 'El producto se ha creado correctamente.',
                                    'product' => $product
                                ];
                            } else {
                                $data = [
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'El id de el proveedor no es válido.'
                                ];
                            }
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'El id de la categoría no es válido.'
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'El producto ya existe.'
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
            'message' => 'No se pudo actualizar el producto.'
        ];

        if ($authCheck['auth']) {
            $em = $this->getDoctrine()->getManager();
            $productRepo = $this->getDoctrine()->getRepository(Product::class);
            
            $json = $request->get('json', null);
            $params = json_decode($json);

            $product = $productRepo->findOneBy([
                'id' => $params->id
            ]);

            if ($product != null && $json != null) {
                $productName = $product->getName();

                $name = (!empty($params->name)) ? $params->name : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $category_id = (!empty($params->category)) ? $params->category : null;
                $provider_id = (!empty($params->provider)) ? $params->provider : null;

                if (!empty($name) && !empty($category_id) && !empty($provider_id)) {
                    $product->setName($name);
                    $product->setDescription($description);                 
                    $product->setUpdatedAt(new \DateTime('now'));

                    $issetProduct = $productRepo->findBy(array(
                        'name' => $name
                    ));

                    if (count($issetProduct) == 0 || $productName == $name) {
                        $categoryRepo = $this->getDoctrine()->getRepository(Category::class);
                        $category = $categoryRepo->findOneBy([
                            'id' => $category_id
                        ]);
                        if ($category != null) {
                            $product->setCategory($category);

                            $providerRepo = $this->getDoctrine()->getRepository(Provider::class);
                            $provider = $providerRepo->findOneBy([
                                'id' => $provider_id
                            ]);
                            if ($provider != null) {
                                $product->setProvider($provider);

                                $em->persist($product);
                                $em->flush();

                                $data = [
                                    'status' => 'success',
                                    'code' => '200',
                                    'message' => 'El producto ha sido actualizado.',
                                    'product' => $product
                                ];
                            } else {
                                $data = [
                                    'status' => 'error',
                                    'code' => 200,
                                    'message' => 'El id de el proveedor no es válido.'
                                ];
                            }
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'El id de la categoría no es válido.'
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => '400',
                            'message' => 'El producto ya existe.'
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
            'message' => 'No se pudo eliminar el producto.'
        ];

        if ($authCheck['auth']) {
            $productoRepo = $this->getDoctrine()->getRepository(Producto::class);
            $product = $productoRepo->findOneBy([
                'id' => $id
            ]);

            if ($product != null) {
                $em = $this->getDoctrine()->getManager();
                $em->remove($product);
                $em->flush();

                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'message' => 'El producto ha sido eliminado.',
                    'product' => $product
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
}
