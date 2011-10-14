<?php

$app = require_once __DIR__.'/bootstrap.php';

/**
 * Homepage, lists recent projects
 */
$app->get('/', function() use ($app) {
    $projects = $app['projects']->findHomepage($app['session']->get('username'));
    $comments = $app['comments']->findLatests();

    return $app['twig']->render('homepage.html.twig', array(
        'projects' => $projects,
        'comments' => $comments,
    ));
})->bind('homepage');

/**
 * Logout
 */
$app->get('logout', function() use ($app) {
    $app['session']->remove('username');

    return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('logout');

/**
 * Adds a comment to a project
 */
 $app->post('/project/{id}/comment', function($id) use ($app) {
    $form = $app['form.factory']->create(new Form\CommentType(), new Entity\Comment());
    $form->bindRequest($app['request']);

    if ($form->isValid()) {
        $comment = (array) $form->getData();

        unset($comment['id']);

        $comment['project_id']   = $id;
        $comment['username']     = $app['session']->get('username');
        $comment['content_html'] = $app['markdown']($comment['content']);

        $app['comments']->insert($comment);
        $app['projects']->update(array('last_commented_at' => date('Y-m-d H:i:s')), array('id' => $id));

        return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
    }

    $project  = $app['projects']->find($id);
    $comments = $app['comments']->findByProjectId($id);

    return $app['twig']->render('Project/show.html.twig', array(
        'form'     => $form->createView(),
        'project'  => $project,
        'comments' => $comments,
    ));
 })->bind('project_comment');

 /**
  * Adds a link to a project
  */
$app->post('/project/{id}/link', function($id) use ($app) {
    $form = $app['form.factory']->create(new Form\ProjectLinkType(), new Entity\ProjectLink());
    $form->bindRequest($app['request']);

    if ($form->isValid()) {
        $projectLink = (array) $form->getData();

        unset($projectLink['id']);

        $projectLink['project_id'] = $id;

        $app['project_links']->insert($projectLink);

        return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
    }

    $project  = $app['projects']->find($id);
    $comments = $app['comments']->findByProjectId($id);

    return $app['twig']->render('Project/show.html.twig', array(
        'form'     => $form->createView(),
        'project'  => $project,
        'comments' => $comments,
    ));
})->bind('project_link');

/**
 * Deletes a project
 */
$app->post('/project/{id}/delete', function($id) use ($app) {
   $app['projects']->delete(array('id' => $id));
   return $app->redirect($app['url_generator']->generate('homepage'));
})->bind('project_delete');

/**
 * Shows the edit form for a project
 */
$app->get('/project/{id}/edit', function($id) use ($app) {
    $project = $app['hydrate'](new Entity\Project(), $app['projects']->find($id));
    $form    = $app['form.factory']->create(new Form\ProjectType(), $project);

    return $app['twig']->render('Project/edit.html.twig', array(
        'form'    => $form->createView(),
        'project' => $project
    ));

})->bind('project_edit');

/**
 * Actually updates a project
 */
$app->post('/project/{id}', function($id) use ($app) {
    $project = $app['hydrate'](new Entity\Project(), $app['projects']->find($id));
    $form    = $app['form.factory']->create(new Form\ProjectType(), $project);

    $form->bindRequest($app['request']);

    if ($form->isValid()) {
        $project = (array) $form->getData();

        $project['id'] = $id;
        $project['description_html'] = $app['markdown']($project['description']);

        $app['projects']->update($project, array('id' => $id));

        return $app->redirect($app['url_generator']->generate('project_show', array('id' => $id)));
    }

    return $app['twig']->render('Project/edit.twig.html', array(
        'form'    => $form->createView(),
        'project' => $project,
    ));
})->bind('project_update');

/**
 * Project creation form
 */
$app->get('/project/new', function() use ($app) {
    $form = $app['form.factory']->create(new Form\ProjectType(), new Entity\Project());

    return $app['twig']->render('Project/new.html.twig', array(
        'form' => $form->createView(),
    ));
})->bind('project_new');

/**
 * Project show
 */
$app->get('/project/{id}/{allComments}', function($id, $allComments = false) use ($app) {
    $project    = $app['projects']->findWithHasVoted($id, $app['session']->get('username'));
    $comments   = $app['comments']->findByProjectId($id, $allComments ? 0 : 5);
    $nbComments = $app['comments']->countByProjectId($id);
    $voters     = $app['project_votes']->findByProjectId($id);
    $links      = $app['project_links']->findByProjectId($id);

    $form       = $app['form.factory']->create(new Form\CommentType(), new Entity\Comment());
    $linkForm   = $app['form.factory']->create(new Form\ProjectLinkType(), new Entity\ProjectLink());

    return $app['twig']->render('Project/show.html.twig', array(
        'form'             => $form->createView(),
        'link_form'        => $linkForm->createView(),
        'project'          => $project,
        'comments'         => $comments,
        'skipped_comments' => $nbComments - count($comments),
        'voters'           => $voters,
        'links'            => $links,
    ));
})->bind('project_show')->value('allComments', false);

/**
 * Project creation
 */
$app->post('/project', function() use ($app) {
    $form = $app['form.factory']->create(new Form\ProjectType(), new Entity\Project());

    $form->bindRequest($app['request']);

    if ($form->isValid()) {

        $project = (array) $form->getData();

        unset($project['id']);

        $project['username']         = $app['session']->get('username');
        $project['description_html'] = $app['markdown']($project['description']);

        $app['projects']->insert($project);

        return $app->redirect('/');
    }

    return $app['twig']->render('Project/new.html.twig', array(
        'form' => $form->createView(),
    ));

})->bind('project_create');

/**
 * Deletes a comment
 */
$app->post('/comment/{id}/delete', function($id) use ($app) {
    $comment = $app['comments']->find($id);
    $app['comments']->delete(array('id' => $id));

    return $app->redirect($app['url_generator']->generate('project_show', array('id' => $comment['project_id'])));
})->bind('comment_delete');

/**
 * Vote for project
 */
$app->get('/project/{id}/vote', function($id) use ($app) {
    if (!$app['project_votes']->existsForProjectAndUser($id, $app['session']->get('username'))) {
        $app['project_votes']->insert(array(
            'username'   => $username,
            'project_id' => $id,
        ));
    }

    return $app->redirect(urldecode($app['request']->query->get('return_url', '/')));
})->bind('project_vote');

/**
* Unvote project
*/
$app->get('/project/{id}/unvote', function($id) use ($app) {
    $app['project_votes']->delete(array(
        'project_id' => $id,
        'username'   => $app['session']->get('username'),
    ));

    return $app->redirect(urldecode($app['request']->query->get('return_url', '/')));
})->bind('project_unvote');

return $app;
