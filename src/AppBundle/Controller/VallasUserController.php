<?php

namespace AppBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use ESocial\AdminBundle\Controller\UserController;
use ESocial\UtilBundle\Util\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Vallas\ModelBundle\Entity\SecuritySubmodulePermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Vallas\ModelBundle\Entity\User;
use Vallas\ModelBundle\Entity\UserPais;

/**
 * Class VallasUserController
 * @package AppBundle\Controller
 * @author Débora Vázquez Lara <debora.vazquez@gmail.com>
 */

/**
 * VallasUserController.
 * @Route("/{_locale}/user", defaults={"_locale"="en"})
 */
class VallasUserController extends UserController {

    public function getSessionCountry(){

        $em = $this->getDoctrine()->getManager();
        $request = $this->get('request_stack')->getCurrentRequest();
        $session = $request->getSession();
        $vallas_country = $session->get('vallas_country');
        $vallas_country_id = $vallas_country ? $vallas_country['code'] : null;
        if ($vallas_country_id){
            return $em->getRepository('VallasModelBundle:Pais')->find($vallas_country_id);
        }
        return null;
    }

    private function prepareRolePermissions($entity){

        $em = $this->getDoctrine()->getManager();
        $roles = $entity->getRoles();

        if (count($roles) < 1) return;

        $role = $em->getRepository('VallasModelBundle:Role')->findOneBy(array('code' => $roles[0]));

        $qb = $em->getRepository('VallasModelBundle:SecuritySubmodule')
            ->createQueryBuilder('s')
            ->addSelect('permissions')
            ->leftJoin('s.permissions', 'permissions', 'WITH', 'permissions.role = :role AND (permissions.user IS NULL OR permissions.user = :user)')
            ->setParameter('role', $role->getId())
            ->setParameter('user', $entity->getId())
            ->orderBy('s.name');

        $submodules = $qb->getQuery()->getResult();
        $permissions = new ArrayCollection();

        if (count($submodules) > 0){
            foreach($submodules as $sm){
                $strCRUD = null;
                if (count($sm->getPermissions()) > 0) {
                    $smPermission = $sm->getPermissions()[0];
                    $strCRUD = $smPermission->getPermissions();
                }
                $smPermission = new SecuritySubmodulePermission();
                if ($roles[0] == 'ROLE_CUSTOM'){
                    $smPermission->setUser($entity);
                    $smPermission->setRole($role);
                }
                $smPermission->setSubmodule($sm);
                $smPermission->setPermissions($strCRUD);
                $smPermission->setPais($this->getSessionCountry());

                $permissions->add($smPermission);
            }
        }
        $entity->setPermissions($permissions);
    }

    /**
     * @Route("/{token}/update", name="esocial_admin_user_update", options={"expose"=true})
     * @Method("POST")
     */
    public function updateAction(Request $request, $token){

        $em = $this->getDoctrine()->getManager();

        $esocialAdminUserType = $this->getEsocialAdminUserType();
        $entity = $em->getRepository($this->getESocialAdminUserClass())->getOneByToken($token, array('user_paises' => null));
        $this->prepareRolePermissions($entity);

        $form = $this->createForm(new $esocialAdminUserType(), $entity, array('data_class' => $this->getESocialAdminUserClass(), 'role_class' => $this->getEsocialAdminRoleClass()));

        $boolSaved = $this->saveAction($request, $entity, $form);

        if ($boolSaved){
            return $this->redirect($this->generateUrl('esocial_admin_user_edit', array('token' => $entity->getToken())));
        }

        return $this->render('ESocialAdminBundle:screens/user:form.html.twig', array(
            'entity' => $entity,
            'form' => $form->createView(),
        ));

    }

    /**
     * @Route("/{token}/edit", name="esocial_admin_user_edit", options={"expose"=true})
     * @Method("GET")
     */
    public function editAction($token){

        $em = $this->getDoctrine()->getManager();

        $esocialAdminUserType = $this->getEsocialAdminUserType();
        $entity = $em->getRepository($this->getESocialAdminUserClass())->getOneByToken($token, array('user_paises' => null));
        $this->prepareRolePermissions($entity);

        $form = $this->createForm(new $esocialAdminUserType(), $entity, array('data_class' => $this->getESocialAdminUserClass(), 'role_class' => $this->getEsocialAdminRoleClass()));

        return $this->render('ESocialAdminBundle:screens/user:form.html.twig', array(
            'entity' => $entity,
            'form' => $form->createView(),
        ));

    }

    /**
     * @Route("/add", name="esocial_admin_user_add")
     * @Method("GET")
     */
    public function addAction(){

        $em = $this->getDoctrine()->getManager();
        $esocialAdminUserClass = $this->getESocialAdminUserClass();
        $esocialAdminUserType = $this->getEsocialAdminUserType();

        $entity = new $esocialAdminUserClass();
        $this->initEntity($entity);
        $form = $this->createForm(new $esocialAdminUserType(), $entity, array('data_class' => $this->getESocialAdminUserClass(), 'role_class' => $this->getEsocialAdminRoleClass()));

        return $this->render('ESocialAdminBundle:screens/user:form.html.twig', array(
            'entity' => $entity,
            'form' => $form->createView()
        ));

    }

    /**
     * @Route("/create", name="esocial_admin_user_create")
     * @Method("POST")
     */
    public function createAction(Request $request){

        $em = $this->getDoctrine()->getManager();
        $esocialAdminUserClass = $this->getESocialAdminUserClass();
        $esocialAdminUserType = $this->getEsocialAdminUserType();

        $entity = new $esocialAdminUserClass();
        $this->initEntity($entity);
        $form = $this->createForm(new $esocialAdminUserType(), $entity, array('data_class' => $this->getESocialAdminUserClass(), 'role_class' => $this->getEsocialAdminRoleClass()));

        $boolSaved = $this->saveAction($request, $entity, $form);

        if ($boolSaved){
            return $this->redirect($this->generateUrl('esocial_admin_user_edit', array('token' => $entity->getToken())));
        }

        return $this->render('ESocialAdminBundle:screens/user:form.html.twig', array(
            'entity' => $entity,
            'form' => $form->createView(),
        ));

    }

    private function initEntity($entity){
        $userPais = new UserPais();
        $userPais->setUser($entity);
        $userPais->setPais($this->getSessionCountry());
        $entity->addUserPaise($userPais);
    }

    public function saveAction(Request $request, $entity, $form){

        $em = $this->getDoctrine()->getManager();

        if ($request->getMethod() == 'POST'){

            $original_countries = array();
            foreach($entity->getUserPaises() as $up){
                $original_countries[] = $up;
            }

            $form->handleRequest($request);

            if ($form->isValid()){

                foreach($original_countries as $original_key=>$original_up){
                    $boolDelete = true;
                    foreach($entity->getUserPaises() as $key=>$up){
                        if ($key == $original_key){
                            $boolDelete = false;
                            break;
                        }
                    }
                    if ($boolDelete){
                        $entity->removeUserPaise($original_up);
                        $em->remove($original_up);
                    }
                }

                foreach($entity->getUserPaises() as $userPais){
                    $userPais->setUser($entity);
                }

                Database::saveEntity($em, $entity);

                $this->get('session')->getFlashBag()->add('notice', $this->get('translator')->trans('form.notice.saved_success'));

                return true;

            }else{
                $this->get('session')->getFlashBag()->add('error', $this->get('translator')->trans('form.notice.saved_error'));
                return false;
            }
        }

        return false;

    }

}