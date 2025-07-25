<?php

/*
 * Mail.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2021 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Util;

use App\Facades\LibrenmsConfig;
use LibreNMS\Exceptions\RrdGraphException;
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    /**
     * Parse string with emails. Return array with email (as key) and name (as value)
     *
     * @param  string  $emails
     * @return array|false
     */
    public static function parseEmails($emails)
    {
        $result = [];
        $regex = '/^[\"\']?([^\"\']+)[\"\']?\s{0,}<([^@]+@[^>]+)>$/';
        if (is_string($emails)) {
            $emails = preg_split('/[,;]\s{0,}/', $emails);
            foreach ($emails as $email) {
                if (preg_match($regex, $email, $out, PREG_OFFSET_CAPTURE)) {
                    $result[$out[2][0]] = $out[1][0];
                } else {
                    if (strpos($email, '@')) {
                        $from_name = LibrenmsConfig::get('email_user');
                        $result[$email] = $from_name;
                    }
                }
            }

            return $result;
        }

        // Return FALSE if input not string
        return false;
    }

    /**
     * Send email with PHPMailer
     *
     * @param  array|string  $emails
     * @param  string  $subject
     * @param  string  $message
     * @param  bool  $html
     * @param  bool  $bcc
     * @param  bool|null  $embedGraphs
     * @return bool
     *
     * @throws \PHPMailer\PHPMailer\Exception if delivery fails
     */
    public static function send($emails, $subject, $message, bool $html = false, bool $bcc = false, ?bool $embedGraphs = null): bool
    {
        if (is_array($emails) || ($emails = self::parseEmails($emails))) {
            d_echo("Attempting to email $subject to: " . implode('; ', array_keys($emails)) . PHP_EOL);
            $mail = new PHPMailer(true);
            $mail->Hostname = php_uname('n');

            foreach (self::parseEmails(LibrenmsConfig::get('email_from')) as $from => $from_name) {
                $mail->setFrom($from, $from_name);
            }

            // add addresses
            $addMethod = $bcc ? 'addBCC' : 'addAddress';
            foreach ($emails as $email => $email_name) {
                $mail->$addMethod($email, $email_name);
            }

            $mail->Subject = $subject;
            $mail->XMailer = LibrenmsConfig::get('project_name');
            $mail->CharSet = 'utf-8';
            $mail->WordWrap = 76;
            $mail->Body = $message;
            if ($embedGraphs ?? LibrenmsConfig::get('email_attach_graphs')) {
                self::embedGraphs($mail, $html);
            }
            if ($html) {
                $mail->isHTML();
            }
            switch (strtolower(trim(LibrenmsConfig::get('email_backend')))) {
                case 'sendmail':
                    $mail->Mailer = 'sendmail';
                    $mail->Sendmail = LibrenmsConfig::get('email_sendmail_path');
                    break;
                case 'smtp':
                    $mail->isSMTP();
                    $mail->Host = LibrenmsConfig::get('email_smtp_host');
                    $mail->Timeout = LibrenmsConfig::get('email_smtp_timeout');
                    $mail->SMTPAuth = LibrenmsConfig::get('email_smtp_auth');
                    $mail->SMTPSecure = LibrenmsConfig::get('email_smtp_secure');
                    $mail->Port = LibrenmsConfig::get('email_smtp_port');
                    $mail->Username = LibrenmsConfig::get('email_smtp_username');
                    $mail->Password = LibrenmsConfig::get('email_smtp_password');
                    $mail->SMTPAutoTLS = LibrenmsConfig::get('email_auto_tls');
                    $mail->SMTPDebug = 0;
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => LibrenmsConfig::get('email_smtp_verifypeer', true),
                            'allow_self_signed' => LibrenmsConfig::get('email_smtp_allowselfsigned', false),
                        ],
                    ];
                    break;
                default:
                    $mail->Mailer = 'mail';
                    break;
            }

            return $mail->send();
        }

        throw new \PHPMailer\PHPMailer\Exception('No contacts found');
    }

    /**
     * Search for generated graph links, generate them, attach them to the email and update the url to a cid link
     */
    private static function embedGraphs(PHPMailer $mail, bool $html = false): void
    {
        $body = $mail->Body;

        // search for generated graphs
        preg_match_all('#<img class=\"librenms-graph\" src=\"(.*?)\" ?/?>#', $body, $matches);

        $count = 0;
        foreach (array_combine($matches[1], $matches[0]) as $url => $tag) {
            try {
                $cid = 'graph' . ++$count;

                // fetch image data
                $image = Graph::getImage($url);

                // attach image
                $fileName = substr(Clean::fileName($image->title ?: $cid), 0, 250);
                $mail->addStringEmbeddedImage(
                    $image->data,
                    $cid,
                    $fileName . '.' . $image->fileExtension(),
                    PHPMailer::ENCODING_BASE64,
                    $image->format->contentType()
                );

                // update image tag to link to attached image, or just the image name
                if ($html) {
                    $body = str_replace($url, "cid:$cid", $body);
                } else {
                    $body = str_replace($tag, "[$fileName]", $body);
                }
            } catch (RrdGraphException|\PHPMailer\PHPMailer\Exception $e) {
                report($e);
            }
        }

        $mail->Body = $body;
    }
}
