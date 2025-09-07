Programma per la gestione dei porti comunali e delle liste d'attesa.


**1) Creare i database**
   
   Copiare le query presenti in "create_database.sql"
   Modificare i nomi dei porti e il numero di posti in base alle necessità
   
**2) copiare i file nel proprio server**

**3) Modificare .env.example**
   
   Inserire i propri dati in tutti i campi.
   La registrazione di default è disabilitata in quanto è pensato come software per la gestione interna, però si può abilitare cambiando ALLOW_REGISTRATION in TRUE.
   Togliere ".example" per renderlo definitivo.
    
**4) Modificare /inc/mail_config_example.php**

   Inserire i dati di invio delle e-mail in tutti i campi e poi rinominare il file in mail_config.php.
    
**5) Modificare /app/map/bola.php.example e ritter.php.example**

   Alla fine del file sostituire YOUR_API con la propria API di Google Maps.
    
**6) Se vengono cambiati i nomi dei porti**

   Ricordarsi di modificare tutti i riferimenti agli attuali nomi dei porti in tutti i file (navbar, dashboard, ecc.)