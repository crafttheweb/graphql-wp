<?php

namespace Mohiohio\GraphQLWP\Mutations;

use GraphQL\Type\Definition\Type;
use Mohiohio\GraphQLWP\Type\Definition\User;
use ReallySimpleJWT\Token;
use Ramsey\Uuid\Uuid;


class Login extends MutationInterface
{
    const REFRESH_TOKEN_META_KEY = 'graphql-wp-refresh-token';

    static function getInputFields()
    {
        return [
            'username' => [
                'type' => Type::string()
            ],
            'password' => [
                'type' => Type::string()
            ]
        ];
    }

    static function getOutputFields()
    {
        return [
            'token' => [
                'type' => Type::string(),
                'resolve' => function ($payload) {
                    $secret = getenv('JWT_SECRET', true);

                    $user = $payload['user'];
                    // https://github.com/RobDWaller/ReallySimpleJWT
                    $token = $user ? Token::create($user->ID, $secret, time() + static::get_token_expire_time(), getenv('WP_HOME')) : null;

                    return $token;
                }
            ],
            'refresh_token' => [
                'type' => Type::string(),
                'resolve' => function ($payload) {
                    $secret = getenv('JWT_SECRET', true);
                    $user = $payload['user'];

                    $key = get_user_meta($user->ID, self::REFRESH_TOKEN_META_KEY, true);
                    if (!$key) {
                        $key = Uuid::uuid4()->toString();
                        update_user_meta($user->ID, self::REFRESH_TOKEN_META_KEY, $key);
                    }
                    // https://github.com/RobDWaller/ReallySimpleJWT
                    $token = $user ? Token::create($key, $secret, time() + DAY_IN_SECONDS * 365, getenv('WP_HOME')) : null;

                    return $token;
                }
            ],
            'user' => [
                'type' => User::getInstance()
            ]
        ];
    }

    static function get_token_expire_time()
    {
        return apply_filters('graphql-wp-token-expire-seconds', 3600);
    }

    static function mutateAndGetPayload($input)
    {

        $secret = getenv('JWT_SECRET', true);
        if (!$secret) {
            throw new \Exception('JWT_SECRET environment variable not set');
        }
        $res = wp_authenticate($input['username'], $input['password']);
        $is_error = is_wp_error($res);


        return [
            'user' => $is_error ? null : $res,
            'error' => $is_error ? $res : null
        ];
    }
}
