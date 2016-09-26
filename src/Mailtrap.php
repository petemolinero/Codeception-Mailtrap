<?php

namespace Codeception\Module;

use Codeception\Module;
use GuzzleHttp\Client;

/**
 * This module allows you to test emails using Mailtrap <https://mailtrap.io>.
 * Please try it and leave your feedback.
 *
 * ## Project repository
 *
 * <https://github.com/WhatDaFox/Codeception-Mailtrap>
 *
 * ## Status
 *
 * * Maintainer: **Valentin Prugnaud**
 * * Stability: **dev**
 * * Contact: valentin@whatdafox.com
 *
 * ## Config
 *
 * * client_id: `string`, default `` - Your mailtrap API key.
 * * inbox_id: `string`, default `` - The inbox ID to use for the tests
 *
 * ## API
 *
 * * client - `GuzzleHttp\Client` Guzzle client for API requests
 */
class Mailtrap extends Module
{
    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $baseUrl = 'https://mailtrap.io/api/v1/';

    /**
     * @var array
     */
    protected $config = ['client_id' => null, 'inbox_id' => null, 'cleanup' => true];

    /**
     * @var array
     */
    protected $requiredFields = ['client_id', 'inbox_id'];

    /**
     * Initialize.
     *
     * @return void
     */
    public function _initialize()
    {
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers'  => [
                'Api-Token' => $this->config['client_id'],
            ],
        ]);
    }

    /**
     * Clean the inbox after each scenario.
     *
     * @param \Codeception\TestCase $test
     */
    public function _after(\Codeception\TestCase $test)
    {
        if ($this->config['cleanup']) {
            $this->cleanInbox();
        }
    }

    /**
     * Clean all the messages from inbox.
     *
     * @return void
     */
    public function cleanInbox()
    {
        $this->client->patch("inboxes/{$this->config['inbox_id']}/clean");
    }

    /**
     * Check if the latest email received contains $params.
     *
     * @param $params
     *
     * @return mixed
     */
    public function receiveAnEmail($params)
    {
        $message = $this->fetchLastMessage();

        foreach ($params as $param => $value) {
            $this->assertEquals($value, $message[$param]);
        }
    }

    /**
     * Get all messages from the inbox.
     *
     * @return array
     */
    public function fetchAllMessages()
    {
        $messages = $this->client->get("inboxes/{$this->config['inbox_id']}/messages")->getBody();
        $messages = json_decode($messages, true);

        return $messages;
    }


    /**
     * Get the most recent message of the default inbox.
     *
     * @return array
     */
    public function fetchLastMessage()
    {
        $messages = $this->fetchAllMessages();

        return array_shift($messages);
    }

    /**
     * Gets the attachments on the last message.
     *
     * @return array
     */
    public function fetchAttachmentsOfLastMessage()
    {
        $email = $this->fetchLastMessage();
        $response = $this->client->get("inboxes/{$this->config['inbox_id']}/messages/{$email['id']}/attachments")->getBody();

        return json_decode($response, true);
    }

    /**
     * Check if there is any email in the inbox that contains $params.
     *
     * @param $params
     *
     * @return mixed
     */
    public function haveEmailWithinInbox( $params )
    {
        $messages = $this->fetchAllMessages();
        $emailExists = false;

        // Cycle through each of the messages
        foreach( $messages as $message ) {
            $matchingParamsForMessage = 0;

            foreach ($params as $param => $value) {
                
                // Mailtrap seems to add a newline to the end of each 'html_body'. This removes it.
                if ( $param == 'html_body' ) {
                    $message['html_body'] = rtrim( $message['html_body'] );
                }
                
                if ( $value == $message[$param] ) {
                    $matchingParamsForMessage++;
                }
            }

            // If every param had a match, then the requested email exists
            if ( $matchingParamsForMessage == count( $params ) ) {
                $emailExists = true;
                break;
            }
        }

        // If it doesn't exist, fail with a useful message
        if ( !$emailExists ) {
            $this->fail( 'Failed asserting that the specified e-mail exists' );
        }
    }

    /**
     * Check if the latest email received is from $senderEmail.
     *
     * @param $senderEmail
     *
     * @return mixed
     */
    public function receiveAnEmailFromEmail($senderEmail)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($senderEmail, $message['from_email']);
    }

    /**
     * Check if the latest email received is from $senderName.
     *
     * @param $senderName
     *
     * @return mixed
     */
    public function receiveAnEmailFromName($senderName)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($senderName, $message['from_name']);
    }

    /**
     * Check if the latest email was received by $recipientEmail.
     *
     * @param $recipientEmail
     *
     * @return mixed
     */
    public function receiveAnEmailToEmail($recipientEmail)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($recipientEmail, $message['to_email']);
    }

    /**
     * Check if the latest email was received by $recipientName.
     *
     * @param $recipientName
     *
     * @return mixed
     */
    public function receiveAnEmailToName($recipientName)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($recipientName, $message['to_name']);
    }

    /**
     * Check if the latest email received has the $subject.
     *
     * @param $subject
     *
     * @return mixed
     */
    public function receiveAnEmailWithSubject($subject)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($subject, $message['subject']);
    }

    /**
     * Check if the latest email received has the $textBody.
     *
     * @param $textBody
     *
     * @return mixed
     */
    public function receiveAnEmailWithTextBody($textBody)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($textBody, $message['text_body']);
    }

    /**
     * Check if the latest email received has the $htmlBody.
     *
     * @param $htmlBody
     *
     * @return mixed
     */
    public function receiveAnEmailWithHtmlBody($htmlBody)
    {
        $message = $this->fetchLastMessage();
        $this->assertEquals($htmlBody, $message['html_body']);
    }

    /**
     * Look for a string in the most recent email (Text).
     *
     * @param $expected
     *
     * @return mixed
     */
    public function seeInEmailTextBody($expected)
    {
        $email = $this->fetchLastMessage();
        $this->assertContains($expected, $email['text_body'], 'Email body contains text');
    }

    /**
     * Look for a string in the most recent email (HTML).
     *
     * @param $expected
     *
     * @return mixed
     */
    public function seeInEmailHtmlBody($expected)
    {
        $email = $this->fetchLastMessage();
        $this->assertContains($expected, $email['html_body'], 'Email body contains HTML');
    }

    /**
     * Look for an attachment on the most recent email.
     *
     * @param $count
     */
    public function seeAttachments($count)
    {
        $attachments = $this->fetchAttachmentsOfLastMessage();

        $this->assertEquals($count, count($attachments));
    }

    /**
     * Look for an attachment on the most recent email.
     *
     * @param $bool
     */
    public function seeAnAttachment($bool)
    {
        $attachments = $this->fetchAttachmentsOfLastMessage();

        $this->assertEquals($bool, count($attachments) > 0);
    }
}
