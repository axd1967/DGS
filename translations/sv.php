<?php

add_to_known_languages( "sv", "Svenska" );

class sv_Language extends Language
{
  function sv_Language()
    {
      $this->translated_strings =
        array( /* From std_functions.php */
               "Status" =>
               "Status",

               "Messages" =>
               "Meddelanden",

               "Users" =>
               "Användare",

               "Forums" =>
               "Forum",

               "Invite" =>
               "Bjud in",

               "Docs" =>
               "Dokumentation",

               "Logged in as" =>
               "Inloggad som",

               "Not logged in" =>
               "Ej inloggad",

               "Logout" =>
               "Logga ut",

               /* From index.php */
               "Home" =>
               "Hemma",

               "Please login." =>
               "Logga in, tack.",

               "To look around, use %s." =>
               "Använd %s för att se dig omkring.",

               "Register new account" =>
               "Registrera ett nytt konto",

               "Log in" =>
               "Logga in",

               "Forgot password?" =>
               "Glömt lösenordet?",

               /* Other things */
               "Userid" =>
               "Användaridentitet",

               "Password" =>
               "Lösenord",

               "Yes" =>
               "Ja",

               "No" =>
               "Nej"

               );
    }
};

?>
