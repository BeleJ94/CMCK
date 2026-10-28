<?php
require_once dirname(__DIR__).'/services/CustomerDeliveryService.php';

class CustomerDeliveryController extends Controller
{
    /** The delivery form posts with its own script: the message and the note to print come back with the page. */
    public function store()
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['success'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('distributions');}
        try{$result=(new CustomerDeliveryService())->deliver($_POST,Auth::user());flash('success',$result['message']);flash('delivery_done',(string)$result['id']);if($ajax)return $this->json(['success'=>true,'message'=>$result['message'],'redirect'=>base_url('distributions')]);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['success'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('distributions');
    }
    public function cancel($id){ $this->run(fn($s,$u)=>$s->cancel($id,$_POST,$u),'Livraison annulée : les sacs sont de nouveau réservés à la commande.'); }

    /** Delivery note as a PDF: shown in the browser (preview, print), or downloaded with ?export=pdf. */
    public function print($id)
    {
        $delivery=(new CustomerDeliveryService())->find($id);
        if(!$delivery){http_response_code(404);echo 'Bon de livraison introuvable.';return;}
        header('Cache-Control: private, no-store');
        $number=$delivery['document_number']?:$delivery['delivery_number'];
        try{
            $html=$this->renderViewToString('distributions/delivery_print',['delivery'=>$delivery]);
            (new PdfService())->stream('Bon de livraison',$html,'bon-livraison-'.trim(preg_replace('/[^A-Za-z0-9]+/','-',$number),'-').'.pdf','portrait',($_GET['export']??'')==='pdf');
        }catch(RuntimeException $e){http_response_code(500);echo e($e->getMessage());}
    }

    private function run(callable $callback,string $message)
    {
        $ajax=strtolower($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='xmlhttprequest';
        if(!verify_csrf($_POST['_token']??'')){if($ajax)return $this->json(['ok'=>false,'message'=>'Session expirée. Rechargez la page.'],419);flash('error','Session expirée.');redirect('distributions');}
        try{$callback(new CustomerDeliveryService(),Auth::user());if($ajax)return $this->json(['ok'=>true,'message'=>$message,'refresh_url'=>base_url('distributions')]);flash('success',$message);}
        catch(Throwable $e){$message=$e instanceof PDOException?'Enregistrement impossible. Actualisez la page et vérifiez les informations saisies.':$e->getMessage();if($ajax)return $this->json(['ok'=>false,'message'=>$message],422);flash('error',$message);}
        redirect('distributions');
    }
}
