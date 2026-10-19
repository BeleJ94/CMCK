<?php

class UserController extends Controller
{
    public function index()
    {
        $data=$this->model('User')->administrationData();
        $data['title']='Utilisateurs';$data['success']=flash('success');$data['error']=flash('error');
        $this->view('users.index',$data,'layouts.main');
    }

    public function store()
    {
        try {$this->csrf();$this->model('User')->createAccount($_POST,Auth::user());flash('success','Utilisateur créé. Les accès attribués sont visibles dans sa fiche ; les rôles sensibles attendent une seconde approbation.');}
        catch(Exception $e){flash('error',$e->getMessage());}
        redirect('users');
    }

    public function update($id)
    {
        try {$this->csrf();$this->model('User')->updateAccount((int)$id,$_POST,Auth::user());flash('success','Utilisateur mis à jour.');}
        catch(Exception $e){flash('error',$e->getMessage());}
        redirect('users');
    }

    private function csrf()
    {
        if (!verify_csrf($_POST['_token']??'')) { throw new RuntimeException('Jeton CSRF invalide.'); }
    }
}
