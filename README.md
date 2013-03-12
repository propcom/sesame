# Sesame

Sesame is an Authentication package for FuelPHP. It does not conform to Fuel's own Auth package because I, personally,
prefer it when thought goes into things before people actually make them.

Sheepy said to call it Sentry but didn't tell me that was taken so now it's called Sesame.

## Usage

Sesame aliases the class `\Sesame\Sesame` into the root namespace.

Using Sesame is simple because the concept of auth is considered to have only two functions:

* Authenticating a user Authorising a user

To get the most basic functionality you can simply:

* Load the Sesame package 
* Load the Sesame module 
* Configure `sesame.login_driver` to be `Sesame\Login_Default`
* Add the `/login` route to go to `/sesame/login/login`

Since this does not capture every request and check for logged-in-ness you should also create a `before()` method on
some base controller that uses `\Sesame::instance->user()` and `\Sesame::instance()->login()`.

### Configurable things

This simply lists the Config settings that Sesame will look for at one point or another

* `sesame.drivers.$driver` - Class name for the driver `$driver` 
* `sesame.password.hash_fn` - Hashing function used in `Sesame\Util::hash()`
* `sesame.password.salt` - Salt if required by hash function
* `sesame.template` - Template to use for default login view

### Instances

Since FuelPHP does it and we're going for consistency, Sesame works on an instance basis.

* If you call `Sesame::instance()` you will get an instance of the Sesame class configured with the 'default' 
configuration.
* If you call `Sesame::instance('config')` then it will try to load `sesame.config` from Config, and the login driver
will be `sesame.config.login_driver`. `config` here is of course a placeholder for whatever you call it.
* If you call `Sesame::instance('Class\Name')` then it will use `\Class\Name` as the login driver. If it is ambiguous,
it will first try to find config by the string name, and then try a class by the string name, and then bail.

## Authentication

### Users

The common part is "a user". Sesame doesn't care what you mean by "a user", except to state that it must have a way of
identifying it singly. That is to say, your model must have at least one unique key. Sesame doesn't even care that the
user has a password; all _you_ do is tell it how to find a user later.

The most obvious example is password authentication: a user is identified by their username and proven by their
password. Sesame makes no assumptions about how you prove that a user is who they say they are; you could redirect the
user to OpenID for all Sesame cares. All you have to do is respond accurately when Sesame asks your Login driver to
log a user in.

This puts a lot more onus on you to write a sensible User model.

Sesame doesn't make any assumptions about your user model ecause it doesn't know what model you are using. Instead, you
configure a login driver, and it is the login driver (and any module- or app-specific controllers) that interface with
the user model directly.

### Login

In order to avoid crappy configuration stuff all the time, Sesame uses a driver system that interjects itself between
Auth and the User model. You can configure that, or you can specify which driver you want to use explicitly at runtime.

The driver has three required static methods. 

The first is `login`. It takes no parameters. Sesame will call this method on your login driver when it discovers no
user is currently logged in. Because the driver is so thin, it is encouraged to write app-specific drivers.

The second is `retrieve_user`. This takes one parameter, which is whatever you gave to Sesame. Sesame uses this to
retrieve the user from the session. Since you can identify users in any way you like, when you have authenticated a user
you should provide Sesame with the information you accept in `retrieve_user`.

The third is `make_user`. This is sent user data. You will have collected this data so Sesame just passes it on. It
should return the created user on success, or any false value on failure.

A simple driver implementation follows:

    class Login_Driver 
    { 
        public static function login() 
        { 
            \Response::redirect('/login'); 
        }

        public static function retrieve_user($user_id) 
        { 
            return Model_User::find($user_id); 
        }

        public static function make_user($user_data)
        {
            // Probably better to do something like Model_User::signup($user_data).
            // This is just an example.
            $u = Model_User::forge();
            $u->username = $user_data['username'];
            $u->password = \Sesame\Util::hash($user_data['password']);
            $u->save();

            return $u;
        }
    }

`login` is an obvious place to redirect users to an external service, or basically to do whatever. The builtin login
driver (`Login_Default`) does this, but also stashes the originally-requested URI in the session for later use.

Since this represents a break in processing - the request ends! - Sesame requires you to tell it when login has
succeeded. This can be done in one of two ways:

* If the request has to end, call `Sesame::instance()->user_ok` with the data that `retrieve_user` expects.  
* If the user can be authorised within the same request, simply return that data from the `login` method.

Remember, that data will be stored in the session and used each request to retrieve the user, so make sure that you
provide Sesame with enough information to retrieve your user.

The default controller, driver, and user model in the Sesame module should be well-commented enough to explain the
procedure and get you running to write your own drivers.

### Logout

Simply call `Sesame::instance()->logout()`. This will clear the session and unset the user. You probably will want to
force a redirect immediately afterward.

### Signup

The Sesame instance provides a `signup()` method, to which you can pass any user data required to create a user. This
will be directly passed to the `make_user` method of your driver, so really this is just a convenient way of deciding
which driver it goes to by getting Sesame to do it.

### Extending Sesame

The Sesame class uses `static::` and `$this->` in all situations, meaning it can be extended and overridden without
concern.

## Authorisation

Authorisation is the association of users with permissions, and as such defines the concept of a user role and a user
permission. Authorisation is performed with the ACL class.

Authorisation is how you determine that a user can perform an action. To use the authorisation part of Sesame your user
model has to have a method to return the set of permissions available to that user and a method to return the user roles
the user is a member of.

Implementations are free to group permissions and roles however they like; so long as the User model can return its
roles and permissions, since this is all the ACL cares about.

Since it is a common case, the ACL also understands the idea of URL access. This part of ACL is configured in
bootstraps, by telling the ACL what permissions and roles can access what URLs.
