<?php

include('../../../inc/includes.php');

Session::checkLoginUser(); // vérifie juste que l'utilisateur est connecté

header('Content-Type: application/json; charset=utf-8');

$action  = $_GET['action']  ?? '';
$message = $_GET['message'] ?? '';

$chat = new PluginGlpiaichatChat();

if ($action === 'create_ticket') {
   $question = $_GET['question'] ?? '';
   $answer   = $_GET['answer']   ?? '';
   $res      = $chat->createTicketFromChat($question, $answer);
   echo json_encode($res);
   exit;
}

// Message normal (chat)
$response = $chat->handleMessage($message);
echo json_encode($response);
