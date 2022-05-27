<?php
/**
 * Plugin Name: Soisy callback
 * Description: Soisy callback plugin for WooCommerce (change order status (completed or cancelled) via REST API
 * callback)
 *
 * Version: 1.0.0
 * Author: alfiopiccione <alfio.piccione@gmail.com>
 * Author URI: https://alfiopiccione.com/
 * License GPL 2
 * Domain: soisy_cb
 *
 * Copyright (C) 2022 alfiopiccione <alfio.piccione@gmail.com>
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

define('SOISY_CALLBACK_TIME_ZONE', 'Europe/Rome');
define('SOISY_CALLBACK_LOG', true);
define('SOISY_CALLBACK_DIR', plugin_dir_path(__FILE__));

/**
 * Rest API callback
 *
 * @see https://www.soisy.it/integrazione/api-callback/
 */
add_action('rest_api_init', function () {
    // Register the route
    register_rest_route('api/v1', '/soisy_callback/', array(
        'methods'             => 'POST',
        'callback'            => function ($request) {
            $response = [
                'eventId'        => null,
                'eventMessage'   => null,
                'orderReference' => null,
                'orderToken'     => null,
            ];

            if (! $request instanceof \WP_REST_Request) {
                return $request;
            }

            $params = $request->get_params();

            $orderToken     = filter_var($params['orderToken'], FILTER_UNSAFE_RAW);
            $eventId        = filter_var($params['eventId'], FILTER_UNSAFE_RAW);
            $eventMessage   = filter_var($params['eventMessage'], FILTER_UNSAFE_RAW);
            $orderReference = filter_var($params['orderReference'], FILTER_SANITIZE_NUMBER_INT);

            $change = false;
            $note   = null;

            if ('LoanWasDisbursed' === $eventId && $orderReference) {
                // Set order status completed
                $order = wc_get_order($orderReference);
                if ($order instanceof WC_Order) {
                    $note   = __('SoiSy CallBack eventId LoanWasDisbursed', 'soisy_cb');
                    $change = $order->update_status('wc-completed', $note);
                }
            } elseif ('UserWasRejected' === $eventId && $orderReference) {
                // Set order status cancelled
                $order = wc_get_order($orderReference);
                if ($order instanceof WC_Order) {
                    $note   = __('SoiSy CallBack eventId UserWasRejected', 'soisy_cb');
                    $change = $order->update_status('wc-cancelled', $note);
                }
            }

            if ($change) {
                $response = [
                    'orderToken'     => $orderToken,
                    'eventId'        => $eventId,
                    'eventMessage'   => $eventMessage,
                    'orderReference' => $orderReference,
                    'orderNote'      => $note,
                ];

                // Log
                $message = json_encode($response, true);
                $date    = getDateTime();
                if (defined('SOISY_CALLBACK_LOG') && SOISY_CALLBACK_LOG) {
                    $message = "{$date}: {$message}\n";
                    error_log($message, 3, SOISY_CALLBACK_DIR . 'log/soisy_callback.log');
                }
            }

            return new \WP_REST_Response($response);
        },
        'permission_callback' => '__return_true',
        'args'                => array(
            'orderToken'     => array(
                'required' => false,
            ),
            'eventId'        => array(
                'required' => true,
            ),
            'eventMessage'   => array(
                'required' => true,
            ),
            'orderReference' => array(
                'required' => true,
            ),
        ),
    ));
});

/**
 * getDateTime
 *
 * @return string
 */
function getDateTime()
{
    try {
        $timeZone = apply_filters('soisy_callback_timezone', SOISY_CALLBACK_TIME_ZONE);
        $timeZone = new \DateTimeZone($timeZone);
        $dateTime = new \DateTime('now');
        $dateTime->setTimezone($timeZone);

        return $dateTime->format('d/m/Y H:i');
    } catch (Exception $e) {
        return '';
    }
}

