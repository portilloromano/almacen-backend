<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

use Knp\Component\Pager\PaginatorInterface;

use App\Entity\Movement;
use App\Entity\User;
use App\Entity\Product;
use App\Services\JwtAuth;

/**
 * @Route("/movement")
 */
class MovementController extends AbstractController
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

            $movementRepro = $this->getDoctrine()->getRepository(Movement::class);
            $query = $movementRepro->findAll();

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
                'movements' => $pagination
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

            $productRepro = $this->getDoctrine()->getRepository(Movement::class);
            $sql = $productRepro->createQueryBuilder('p')
                ->where('p.description LIKE :search')
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
                'movements' => $pagination
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

            $movementRepro = $this->getDoctrine()->getRepository(Movement::class);
            $movement = $movementRepro->findOneBy([
                'id' => $id
            ]);

            if ($movement != null) {
                $data = [
                    'status' => 'success',
                    'code' => '200',
                    'movement' => $movement
                ];
            } else {
                $data = [
                    'status' => 'error',
                    'code' => '400',
                    'message' => 'No se encontró el movimiento.',
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
        $identity = $jwtAuth->checkToken($token, true);

        $data = [
            'status' => 'error',
            'code' => 400,
            'message' => 'El movimiento no se ha podido crear.'
        ];

        if ($authCheck['auth']) {
            $json = $request->get('json', null);
            $params = json_decode($json);

            if ($json != null) {
                $type = (!empty($params->type)) ? $params->type : null;
                $description = (!empty($params->description)) ? $params->description : null;
                $quantity = (!empty($params->quantity) && $params->quantity > 0) ? $params->quantity : null;
                $user_id = (!empty($identity->sub)) ? $identity->sub : null;
                $product_id = (!empty($params->productId)) ? $params->productId : null;

                if (!empty($type) && !empty($description) && !empty($user_id) && !empty($product_id) && !empty($quantity)) {
                    $userRepo = $this->getDoctrine()->getRepository(User::class);
                    $user = $userRepo->findOneBy([
                        'id' => $user_id
                    ]);

                    $productRepo = $this->getDoctrine()->getRepository(Product::class);
                    $product = $productRepo->findOneBy([
                        'id' => $product_id
                    ]);

                    $movement = new Movement();
                    $movement->setType($type);
                    $movement->setDescription($description);
                    $movement->setQuantity($quantity);
                    $movement->setUser($user);
                    $movement->setProduct($product);
                    $movement->setCreatedAt(new \DateTime('now'));

                    if ($user != null) {
                        if ($product != null) {
                            $productQuantity = $product->getQuantity();
                            If($type == 'Entrada'){
                                $product->setQuantity($productQuantity + $quantity);
                            }elseif($type == 'Salida'){
                                $product->setQuantity($productQuantity - $quantity);
                            }
                            
                            $em = $this->getDoctrine()->getManager();
                            $em->persist($product);
                            $em->flush();

                            $em->persist($movement);
                            $em->flush();

                            $data = [
                                'status' => 'success',
                                'code' => 200,
                                'message' => 'El movimiento se ha creado correctamente.',
                                'movement' => $movement
                            ];
                        } else {
                            $data = [
                                'status' => 'error',
                                'code' => 200,
                                'message' => 'El id de el producto no es válido.'
                            ];
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'El id de el usuario no es válido.'
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
}
