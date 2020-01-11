<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addGlobal('user', $app['session']->get('user'));

    return $twig;
}));


$app->get('/', function () use ($app) {
    return $app['twig']->render('index.html', [
        'readme' => file_get_contents('README.md'),
    ]);
});


$app->match('/login', function (Request $request) use ($app) {
    $username = $request->get('username');
    $password = $request->get('password');

    if ($username) {
        $sql = "SELECT * FROM users WHERE username = '$username' and password = '$password'";
        $user = $app['db']->fetchAssoc($sql);

        if ($user){
            $app['session']->set('user', $user);
            return $app->redirect('/todo');
        }
    }

    return $app['twig']->render('login.html', array());
});


$app->get('/logout', function () use ($app) {
    $app['session']->set('user', null);
    return $app->redirect('/');
});


$app->get('/todo/{id}', function ($id) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    if ($id){
        $sql = "SELECT * FROM todos WHERE id = '$id'";
        $todo = $app['db']->fetchAssoc($sql);

        return $app['twig']->render('todo.html', [
            'todo' => $todo,
        ]);
    } else {
        $sql = "SELECT * FROM todos WHERE user_id = '${user['id']}'";
        $todos = $app['db']->fetchAll($sql);

        return $app['twig']->render('todos.html', [
            'todos' => $todos,
        ]);
    }
})
->value('id', null);


$app->post('/todo/add', function (Request $request) use ($app) {
    if (null === $user = $app['session']->get('user')) {
        return $app->redirect('/login');
    }

    $user_id = $user['id'];
    $description = $request->get('description');

    // if description is blank just give an error message as we do not want user to add entry without desc
    if($description === ""){
        $app['session']->getFlashBag()->add('todoMessages', array("type"=>"danger", "message"=>'Please enter value for description'));
        return $app->redirect('/todo');
    }

    $sql = "INSERT INTO todos (user_id, description) VALUES (?, ?)";
    $app['session']->getFlashBag()->add('todoMessages', array("type"=>"success", "message"=>'Added successfully'));
    $app['db']->executeUpdate($sql, array($user_id, $description));

    return $app->redirect('/todo');
});


$app->match('/todo/delete/{id}', function ($id) use ($app) {

    $sql = "DELETE FROM todos WHERE id = ?";
    $app['db']->executeUpdate($sql, array($id));

    $app['session']->getFlashBag()->add('todoMessages', array("type"=>"success", "message"=>'Removed successfully'));
    return $app->redirect('/todo');
});

$app->match('/todo/complete/{id}', function ($id) use ($app) {
    // update value to 1 to mark todo as completed
    $sql = "UPDATE `todos` SET `is_complete` = ? WHERE `todos`.`id` = ?";
    $app['db']->executeUpdate($sql, array('1' , $id));

    //set success message when marked as completed
    $app['session']->getFlashBag()->add('todoMessages', array("type"=>"success", "message"=>'Marked as completed successfully'));
    return $app->redirect('/todo');
});

$app->match('/todo/{id}/json', function ($id) use ($app) {

    // join query to fetch username from users table
    $sql = "SELECT todos.id, user_id, users.username, description, is_complete FROM `todos` JOIN users ON user_id = users.id where todos.id = ?";
    $todoObj = $app['db']->fetchAssoc($sql, array($id));

    $response = new \Symfony\Component\HttpFoundation\JsonResponse();
    $response->setContent(json_encode($todoObj));

    return $response;
});