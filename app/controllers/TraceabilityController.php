<?php

class TraceabilityController extends Controller
{
    public function index()
    {
        $model=$this->model('Traceability');$filters=$model->filters();
        $this->view('traceability.index',['title'=>'Traçabilité complète','filters'=>$filters,'sites'=>$model->sites(),'results'=>$model->search($filters),'dossier'=>null,'error'=>flash('error')],'layouts.main');
    }

    public function show($chain,$id)
    {
        $model=$this->model('Traceability');$filters=$model->filters();
        try{$dossier=$model->dossier($chain,(int)$id);$error=null;}catch(Exception$e){$dossier=[];$error=$e->getMessage();http_response_code(404);}
        $this->view('traceability.index',['title'=>'Traçabilité complète','filters'=>$filters,'sites'=>$model->sites(),'results'=>[],'dossier'=>$dossier,'chain'=>$chain,'rootId'=>(int)$id,'error'=>$error],'layouts.main');
    }
}
