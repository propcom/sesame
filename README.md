# Century

Century is an Authentication package for FuelPHP. It does not conform to Fuel's own Auth package because I, personally,
prefer it when thought goes into things before people actually make them.

Sheepy said to call it Sentry but didn't tell me that was taken so now it's called Century.

## Usage

Century aliases the class `\Century\Century` into the root namespace.

Using Century is simple because the concept of auth is considered to have only two functions:

* Authenticating a user Authorising a user

To get the most basic functionality you can simply:

* Load the Century package 
* Load the Century module 
* Configure `century.login_driver` to be `Century\Login_Default`
* Add the `/login` route to go to `/century/login/login`

Since this does not capture every request and check for logged-in-ness you should also create a `before()` method on
some base controller that uses `\Century::instance->user()` and `\Century::instance()->login()`.

### Configurable things

This simply lists the Config settings that Century will look for at one point or another

* `century.drivers.$driver` - Class name for the driver `$driver` 
* `century.password.hash_fn` - Hashing function used in `Century\Util::hash()`
* `century.password.salt` - Salt if required by hash function
* `century.template` - Template to use for default login view

### Instances

Since FuelPHP does it and we're going for consistency, Century works on an instance basis.

* If you call `Century::instance()` you will get an instance of the Century class configured with the 'default' 
configuration.
* If you call `Century::instance('config')` then it will try to load `century.config` from Config, and the login driver
will be `century.config.login_driver`. `config` here is of course a placeholder for whatever you call it.
* If you call `Century::instance('Class\Name')` then it will use `\Class\Name` as the login driver. If it is ambiguous,
it will first try to find config by the string name, and then try a class by the string name, and then bail.

## Authentication

### Users

The common part is "a user". Century doesn't care what you mean by "a user", except to state that it must have a way of
identifying it singly. That is to say, your model must have at least one unique key. Century doesn't even care that the
user has a password; all _you_ do is tell it how to find a user later.

The most obvious example is password authentication: a user is identified by their username and proven by their
password. Century makes no assumptions about how you prove that a user is who they say they are; you could redirect the
user to OpenID for all Century cares. All you have to do is respond accurately when Century asks your Login driver to
log a user in.

This puts a lot more onus on you to write a sensible User model.

Century doesn't make any assumptions about your user model ecause it doesn't know what model you are using. Instead, you
configure a login driver, and it is the login driver (and any module- or app-specific controllers) that interface with
the user model directly.

### Login

In order to avoid crappy configuration stuff all the time, Century uses a driver system that interjects itself between
Auth and the User model. You can configure that, or you can specify which driver you want to use explicitly at runtime.

The driver has three required static methods. 

The first is `login`. It takes no parameters. Century will call this method on your login driver when it discovers no
user is currently logged in. Because the driver is so thin, it is encouraged to write app-specific drivers.

The second is `retrieve_user`. This takes one parameter, which is whatever you gave to Century. Century uses this to
retrieve the user from the session. Since you can identify users in any way you like, when you have authenticated a user
you should provide Century with the information you accept in `retrieve_user`.

The third is `make_user`. This is sent user data. You will have collected this data so Century just passes it on. It
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
            $u->password = \Century\Util::hash($user_data['password']);
            $u->save();

            return $u;
        }
    }

`login` is an obvious place to redirect users to an external service, or basically to do whatever. The builtin login
driver (`Login_Default`) does this, but also stashes the originally-requested URI in the session for later use.

Since this represents a break in processing - the request ends! - Century requires you to tell it when login has
succeeded. This can be done in one of two ways:

* If the request has to end, call `Century::instance()->user_ok` with the data that `retrieve_user` expects.  
* If the user can be authorised within the same request, simply return that data from the `login` method.

Remember, that data will be stored in the session and used each request to retrieve the user, so make sure that you
provide Century with enough information to retrieve your user.

The default controller, driver, and user model in the Century module should be well-commented enough to explain the
procedure and get you running to write your own drivers.

### Logout

Simply call `Century::instance()->logout()`. This will clear the session and unset the user. You probably will want to
force a redirect immediately afterward.

### Signup

The Century instance provides a `signup()` method, to which you can pass any user data required to create a user. This
will be directly passed to the `make_user` method of your driver, so really this is just a convenient way of deciding
which driver it goes to by getting Century to do it.

### Extending Century

The Century class uses `static::` and `$this->` in all situations, meaning it can be extended and overridden without
concern.

## Authorisation

Authorisation is the association of users with permissions, and as such defines the concept of a user role and a user
permission. Authorisation is performed with the ACL class.

Authorisation is how you determine that a user can perform an action. To use the authorisation part of Century your user
model has to have a method to return the set of permissions available to that user and a method to return the user roles
the user is a member of.

Implementations are free to group permissions and roles however they like; so long as the User model can return its
roles and permissions, since this is all the ACL cares about.

Since it is a common case, the ACL also understands the idea of URL access. This part of ACL is configured in
bootstraps, by telling the ACL what permissions and roles can access what URLs.
