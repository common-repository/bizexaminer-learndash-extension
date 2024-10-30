<?php

namespace BizExaminer\LearnDashExtension\Helper;

/**
 * User related utils/helper functions
 */
class User
{
    /**
     * Gets the first and last name and email of user
     * falls back to nicename/nickname/login
     *
     * @param int $userId
     * @return array $userInfo (array):
     *               'firstName' => (string)
     *               'lastName' => (string)
     *               'email' => (string)
     */
    public static function getUserInfo(int $userId)
    {
        $user = get_userdata($userId);

        $firstName = $user->first_name;
        $lastName = $user->last_name;
        $nickname = $user->nickname;
        $nicename = $user->user_nicename;
        $login = $user->user_login;
        $email = $user->user_email;

        if (empty($firstName)) {
            if (!empty($nickname)) {
                $firstName = $nickname;
            } elseif (!empty($nicename)) {
                $firstName = $nicename;
            } elseif (!empty($login)) {
                $firstName = $login;
            } else {
                $firstName = '';
            }
        }

        if (empty($lastName)) {
            if (!empty($nickname)) {
                $lastName = $nickname;
            } elseif (!empty($nicename)) {
                $lastName = $nicename;
            } elseif (!empty($login)) {
                $lastName = $login;
            } else {
                $lastName = '';
            }
        }

        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email
        ];
    }
}
