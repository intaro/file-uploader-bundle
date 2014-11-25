# File Uploader Bundle #

## About ##

This is Symfony2 bundle. The purpose of this bundle is to simplify the file uploading process.
It moves files to storage after they were uploaded into temporary folder.

There are few supported storage types:
- local filesystem
- amazon s3

## Installation ##

Require the bundle in your composer.json file:

``` json
  {
      "require": {
          "intaro/file-uploader-bundle": "dev-master",
      }
  }
```

Register in AppKernel:

``` php
  // app/AppKernel.php
  
  class AppKernel extends SaasKernel
  {
      public function registerBundles()
      {
          $bundles = array(
              ...
              new Intaro\FileUploaderBundle\IntaroFileUploaderBundle(),
          );
      }
  }
```
Install with composer:

```
$ composer update intaro/file-uploader-bundle
```

## Usage ##

In configs:
``` yml
  # app/config/config.yml
  
  intaro_file_uploader:
    uploaders:
        local:
            image:
                path: http://www.app.local/images/
                create: true
                allowed_types: ['image/jpeg', 'image/png', 'image/gif']
            document:
                directory: path/to/another/attach/dir
                create: true
                allowed_types: ['application/pdf', 'application/rtf', 'application/vnd.ms-office']
        aws_s3:
            video:
                service_id: aws.client_service_name
                path: https://s3-us-west-2.amazonaws.com/bucket-name/iamges/
                bucket_name: some_bucket
                options:
                    create: true
                    acl: public-read
  ```
  
  In code:
  ```php
  
  public function uploadAction()
  {
      $files = $this->getRequest()->files->get('file');
      
      $this->get('intaro.video_uploader')->upload($file);
  }
    
  ```
