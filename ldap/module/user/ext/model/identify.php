<?php
public function identify($account, $password)
{
    // openlog('zentao-ldap', LOG_PID | LOG_PERROR, LOG_LOCAL4);
    // syslog(LOG_DEBUG, "[__FILE__:__FUNCTION__]account: $account, password: $password");
    // closelog();
	// 如果添加$符号，则启用本地账号。
    if (0 == strcmp('$', substr($account, 0, 1))) {
        return parent::identify(ltrim($account, '$'), $password);
    } else {
        // 进行LDAP用户验证
        $user = false;
        $record = $this->dao->select('*')->from(TABLE_USER)
            ->where('account')->eq($account)
            ->andWhere('deleted')->eq(0)
            ->fetch();
        if ($record) {
            $ldap = $this->loadModel('ldap');
            $ldap_account = $this->config->ldap->uid.'='.$account.',ou=People,'.$this->config->ldap->baseDN; // 注意：ldap account要按自己的格式填
            // openlog('zentao-ldap', LOG_PID | LOG_PERROR, LOG_LOCAL4);
            // syslog(LOG_DEBUG, "[__FILE__:__FUNCTION__]ldap account: $ldap_account, password: $password");
            // closelog();
            $pass = $ldap->identify($this->config->ldap->host, $ldap_account, $password);
            if (0 == strcmp('Success', $pass)) {
                $user = $record;
                $ip   = $this->server->remote_addr;
                $last = $this->server->request_time;
                // 禅道有多处地方需要二次验证密码, 所以需要保存密码的 MD5 在 session 中以供后续验证
                $user->password = md5($password);
                // 判断用户是否来自 ldap
                $user->fromldap = true;
                $this->dao->update(TABLE_USER)->set('visits = visits + 1')->set('ip')->eq($ip)->set('ip')->eq($ip)->set('last')->eq($last)->where('account')->eq($account)->exec();
                $user->last = date(DT_DATETIME1, $user->last);

                /* Create cycle todo in login. */
                $todoList = $this->dao->select('*')->from(TABLE_TODO)->where('cycle')->eq(1)->andWhere('account')->eq($user->account)->fetchAll('id');
                $this->loadModel('todo')->createByCycle($todoList);
            } else {
                echo $pass;
            }
        }  

        return $user;
    }
}
