<?php

namespace Ibrows\Bundle\NewsletterBundle\Controller;

use Ibrows\Bundle\NewsletterBundle\Form\NewsletterFormType;

use Ibrows\Bundle\NewsletterBundle\Annotation\Wizard\Annotation as WizardAction;
use Ibrows\Bundle\NewsletterBundle\Annotation\Wizard\AnnotationHandler as WizardActionHandler;
use Ibrows\Bundle\NewsletterBundle\Block\BlockComposition;

use Doctrine\Common\Collections\Collection;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class NewsletterController extends AbstractController
{
	/**
	 * @Route("/", name="ibrows_newsletter_index")
	 */
	public function indexAction()
	{
        $this->setNewsletter(null);
        
        return $this->render($this->getTemplateManager()->getNewsletter('index'), array(
            
		));
	}
    
    /**
	 * @Route("/list", name="ibrows_newsletter_list")
	 */
	public function listAction()
	{
        $this->setNewsletter(null);
        
		return $this->render($this->getTemplateManager()->getNewsletter('list'), array(
            'newsletters' => $this->getMandant()->getNewsletters()
		));
	}
    
    /**
	 * @Route("/edit/redirection/{id}", name="ibrows_newsletter_edit_redirection")
	 */
	public function editredirectionAction($id)
	{
        $newsletter = $this->getNewsletterById($id);
        $this->setNewsletter($newsletter);
        
        return $this->redirect($this->generateUrl('ibrows_newsletter_meta'));
	}
	
	/**
	 * @Route("/create", name="ibrows_newsletter_create")
	 */
	public function createrediractionAction()
	{
		$this->setNewsletter(null);
        
        return $this->redirect($this->generateUrl('ibrows_newsletter_edit'));
	}
	
	/**
	 * @Route("/meta", name="ibrows_newsletter_meta")
	 * @WizardAction(name="meta", number=1, validationMethod="metaValidation")
	 */
	public function metaAction()
	{
        $newsletter = $this->getNewsletter();
        if($newsletter === null){
            $newsletter = $this->getNewsletterManager()->create();
        }
		
		$formtype = $this->getClassManager()->getForm('newsletter');
		$form = $this->createForm(new $formtype(), $newsletter);
		
		$request = $this->getRequest();
		if($request->getMethod() == 'POST'){
			$form->bindRequest($request);
			
			if($form->isValid()){
                $this->setNewsletter($newsletter);
				return $this->redirect($this->getWizardActionAnnotationHandler()->getNextStepUrl());
			}
		}
	
		return $this->render($this->getTemplateManager()->getNewsletter('create'), array(
            'newsletter' => $newsletter,
            'form' => $form->createView(),
            'wizard' => $this->getWizardActionAnnotationHandler(),
		));
	}
	
    public function metaValidation(WizardActionHandler $handler)
    {
        
    }
    
	/**
	 * @Route("/edit", name="ibrows_newsletter_edit")
     * @WizardAction(name="edit", number=2, validationMethod="editValidation")
	 */
	public function editAction()
	{
        if(($response = $this->getWizardActionValidation()) instanceof Response){
            return $response;
        }

        $request = $this->getRequest();
		if($request->getMethod() == 'POST'){
            $blockParameters = array();
            
            $blockPostArray = $request->request->get('block');
            if(!is_array($blockPostArray)){
               $blockPostArray = array(); 
            }
            
            $blockFileArray = $request->files->get('block');
            if(!is_array($blockFileArray)){
               $blockFileArray = array();
            }
            
            foreach($blockPostArray as $blockId => $content){
                $blockParameters[$blockId] = $content;
            }
            
            foreach($blockFileArray as $blockId => $file){
                $blockParameters[$blockId] = $file;
            }
            
            $newsletter = $this->getNewsletter();
            $this->updateBlocksRecursive($newsletter->getBlocks(), $blockParameters);
            $this->setNewsletter($newsletter);
            
            if($request->request->get('continue')){
                return $this->redirect($this->getWizardActionAnnotationHandler()->getNextStepUrl());
            }
		}
        
		return $this->render($this->getTemplateManager()->getNewsletter('edit'), array(
            'blockProviderManager' => $this->getBlockProviderManager(),
            'newsletter' => $this->getNewsletter(),
            'wizard' => $this->getWizardActionAnnotationHandler(),
		));
	}
    
    public function editValidation(WizardActionHandler $handler)
    {
        if(is_null($this->getNewsletter())){
            return $this->redirect($handler->getStepUrl($handler->getLastValidAnnotation()));
        }
    }
    
    /**
	 * @Route("/subscriber", name="ibrows_newsletter_subscriber")
     * @WizardAction(name="subscriber", number=3, validationMethod="subscriberValidation")
	 */
	public function subscriberAction()
	{
        if(($response = $this->getWizardActionValidation()) instanceof Response){
            return $response;
        }
        
        $newsletter = $this->getNewsletter();
        $formtype = $this->getClassManager()->getForm('subscriber');
        $form = $this->createForm(new $formtype(), $newsletter);
        
        $request = $this->getRequest();
        if($request->getMethod() == 'POST'){
	        	$form->bindRequest($request);
	        		
	        	if($form->isValid()){
	        		$this->setNewsletter($newsletter);
	        		return $this->redirect($this->getWizardActionAnnotationHandler()->getNextStepUrl());
	        	}
        }
        
		return $this->render($this->getTemplateManager()->getNewsletter('subscriber'), array(
            'newsletter' => $this->getNewsletter(),
			'form' => $form->createView(),
            'wizard' => $this->getWizardActionAnnotationHandler(),
		));
	}
    
    public function subscriberValidation(WizardActionHandler $handler)
    {
        if(is_null($this->getNewsletter())){
            return $this->redirect($handler->getStepUrl($handler->getLastValidAnnotation()));
        }
    }
    
    /**
	 * @Route("/settings", name="ibrows_newsletter_settings")
     * @WizardAction(name="settings", number=4, validationMethod="settingsValidation")
	 */
	public function settingsAction()
	{
        if(($response = $this->getWizardActionValidation()) instanceof Response){
            return $response;
        }
        
		return $this->render($this->getTemplateManager()->getNewsletter('settings'), array(
            'newsletter' => $this->getNewsletter(),
            'wizard' => $this->getWizardActionAnnotationHandler(),
		));
	}
    
    public function settingsValidation(WizardActionHandler $handler)
    {
        $newsletter = $this->getNewsletter();
        
        if(is_null($newsletter)){
            return $this->redirect($handler->getStepUrl($handler->getLastValidAnnotation()));
        }
        
        if(count($newsletter->getSubscribers()) <= 0){
            return $this->redirect($this->generateUrl('ibrows_newsletter_subscriber'));
        }
    }
	
	/**
	 * @Route("/summary", name="ibrows_newsletter_summary")
     * @WizardAction(name="summary", number=5, validationMethod="summaryValidation")
	 */
	public function summaryAction()
	{
        if(($response = $this->getWizardActionValidation()) instanceof Response){
            return $response;
        }
        
        $newsletter = $this->getNewsletter();
        $renderer = $this->getRendererManager()->get($this->getMandant()->getRendererName());
        
        $newsletteroverview = $renderer->render($newsletter->getDesign(), array(
            'content' => $renderer->render(
                new BlockComposition($this->getBlockProviderManager(), $newsletter->getBlocks())
            )
        ));
        
		return $this->render($this->getTemplateManager()->getNewsletter('summary'), array(
            'newsletteroverview' => $newsletteroverview,
            'newsletter' => $newsletter,
            'wizard' => $this->getWizardActionAnnotationHandler(),
		));
	}
    
    public function summaryValidation(WizardActionHandler $handler)
    {
        $newsletter = $this->getNewsletter();
        
        if(is_null($newsletter)){
            return $this->redirect($handler->getStepUrl($handler->getLastValidAnnotation()));
        }
        
        if(count($newsletter->getSubscribers()) <= 0){
            return $this->redirect($this->generateUrl('ibrows_newsletter_subscriber'));
        }
    }
    
    /**
	 * @Route("/send", name="ibrows_newsletter_send")
	 */
	public function sendAction()
	{
        $newsletter = $this->getNewsletter();
        if(is_null($newsletter)){
            return $this->redirect($this->generateUrl('ibrows_newsletter_index', array(), true));
        }
        
		return $this->render($this->getTemplateManager()->getNewsletter('send'), array(
            'newsletter' => $newsletter
		));
	}
    
    protected function updateBlocksRecursive(Collection $blocks, array $blockParameters)
    {
        foreach($blocks as $block){
            $parameters = isset($blockParameters[$block->getId()]) ? 
                $blockParameters[$block->getId()] : null;

            $provider = $this->getBlockProviderManager()->get($block->getProviderName());
            $provider->updateBlock($block, $parameters);
            
            $this->updateBlocksRecursive($block->getBlocks(), $blockParameters);
        }
    }
}
