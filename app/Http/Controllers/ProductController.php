<?php

namespace App\Http\Controllers;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Http\Request;
use Kreait\Firebase\Auth;
use App\Models\Product;
use Illuminate\Support\Facades\Mail;
use App\Models\ProductImage;
use App\Mail\ProductEmail;
class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *  
     * @return void
     */
    public $database;
    public $auth;

    public function __construct()
    {
        $this->database = Firebase::database();
        $this->auth = Firebase::auth();
    }

    public function index()
    {
        $value = $this->getCurrentProductFirebaseData();
        return response()->json($value);                                
    }

    public function getUserProducts($userid){
        $products = Product::where('user_id', $userid)->get()->toArray();

        foreach ($products as $index => $product){
             $products[$index]['images'] = ProductImage::where('product_id', $product['id'])->get()->toArray();
        }

         

        if(!empty($products)){
            $this->response['status'] = 200;
            $this->response['message'] = "Berhasil Mengambil Data Product";
            $this->response['data'] = $products;

        }else{
            $this->response['status'] = 505;
            $this->response['message'] = "Data Produk Tidak ditemukan.";
            $this->response['data'] = null;

        }
        
        
        
        return response()->json($this->response);

    }

    public function processImages($request, $productid){
        $file_ary = array();
        $file_count  = count($request->file('imagesArray'));
        $a=($request->file('imagesArray'));
        $finalArray=array();
        $file_count;
        for ($i=0; $i<$file_count; $i++) {
                $fileName = time().$a[$i]->getClientOriginalName();
                $folder = 'assets/product_image';
                $finalArray[$i]['image']=$fileName;
                $a[$i]->move($folder,$fileName);
        }

        $productImagesArr = [];

        foreach($finalArray as $image){
            $data = [
                'product_id' => $productid,
                'name' => $image['image'],
            ];

            array_push($productImagesArr, $data);
        }


        ProductImage::insert($productImagesArr);

        return $finalArray;
    }

    public function testCurl(Request $request){

        $img = $request->file('imagefile');

        $cfile = new \CURLFile($img);

        // print_r($img);
        $data = [
            'image' => "1634568237image_2021-04-30_15-44-06.png"
        ];


        $hasil = $this->curlRequest($data);

        var_dump($hasil->is_approved);

        return response()->json($hasil);
    }

    public function imageValidate(){
         $productImg = ProductImage::where('is_approved',null)->get()->toArray();

         foreach($productImg as $val){
            $data = [
                'image' => $val['name']
            ];

            $hasil = $this->curlRequest($data);
            
            if ($hasil->is_approved == false){
                ProductImage::where('id', $val['id'])->update(['is_approved'=>'0']);
            }else{
                ProductImage::where('id', $val['id'])->update(['is_approved'=>'1']);
            }
         }

         return response()->json($productImg);
    }

    public function productValidate(){

        
        $products = Product::where('id',9)->get()->toArray();

        $this->addToFirebase($products[0]);

    
        // foreach($products as $val){ 
        //     $notApproved = (array) null;
        //     $user = $this->auth->getUser($val['user_id']);

            
        //     $productImages = ProductImage::where('product_id', $val['id'])->get()->toArray();
        //     foreach($productImages as $image){
        //         if ($image['is_approved'] == 0) {
        //             array_push($notApproved, $image);
        //         }
        //     }

        //     if (count($notApproved) > 0){
        //         $data = [
        //             'name' => $val['name'],
        //             'username' => $user->email
        //         ];
        
        //         Mail::to($user->email)->send(new ProductEmail($data));
        //     }else{
        //         $data = [
        //             'name' => $val['name'],
        //             'username' => $user->email
        //         ];

        //         Product::where('id',$val['id'])->update(['is_active'=>1]);

        //         $this->addToFirebase($val);
        //     }

        // }


        $this->response['status'] = 200;
        $this->response['data'] = null;
        $this->response['message'] = "Ok";

        return response()->json($this->response);
    }

    public function requestProduct(Request $request){

        $data = $request->all();
        
        
        
        unset($data['imagesArray']);


        $produkid = Product::create($data)->id;
        $imageList = $this->processImages($request, $produkid);

        //save to database
        
        //send curl ke python service
        

        //if approve update data ke database 


        //send data to firebase

        if($produkid){
            $this->response['status'] = 200;
            $this->response['data'] = null;
            $this->response['message'] = "Berhasil Merequest Product";
        }else{
            $this->response['status'] = 505;
            $this->response['data'] = null;
            $this->response['message'] = "Terjadi Kesalahan!";
        }
   

        return response()->json($this->response);

    }

    private function getCurrentProductFirebaseData(){
        
        $reference = $this->database->getReference('/products');
        $snapshot = $reference->getSnapshot();

        $value = $snapshot->getValue();

        return $value;
    }

    public function addToFirebase($productData)
    {
        $data = [
            'product_id'    => $productData['id'],
            'product_name' => $productData['name'],
            'product_slug' => $this->slugify($productData['name']),
            'total_bidder' => "0",
            'highest_bid' => "0",
            'end_date' => $productData['end_date'],
            'initial_price' => $productData['starting_price'],
            'product_images' => [],
        ];

        $currentFirebaseData = $this->getCurrentProductFirebaseData();

        $productImages = ProductImage::where('product_id',$data['product_id'])->get()->toArray();

        for($i = 0; $i < count($productImages); $i++){
            array_push($data['product_images'], 'http://localhost/auction-backend/public/assets/product_image/' . $productImages[$i]['name']);
        }

        $currentFirebaseData[$data['product_id']] = $data;
        $insert = $this->database->getReference('/products')
        ->set($currentFirebaseData);



       return $insert;
    }


    public function create(Request $request)
    {   

        $data = $request->all();
        $isNameSame = false;

        $data['product_images'] = explode(",",$data['product_images']);
        $data['product_slug'] = $this->slugify($data['product_name']);

        $arrData[$data['product_slug']] =  $data;

        $currentFirebaseData = $this->getCurrentProductFirebaseData();

        if($currentFirebaseData[$data['product_slug']]){
            $isNameSame = true;
        }else{
            $currentFirebaseData[$data['product_slug']] = $data;
            $insert = $this->database->getReference('/products')
            ->set($currentFirebaseData);
        }
       
        if(!$isNameSame){
            $this->response['status'] = 200;
            $this->response['data'] = null;
            $this->response['message'] = "OK";
        }else{
            $this->response['status'] = 505;
            $this->response['data'] = null;
            $this->response['message'] = "Nama Produk Sama!";
        }
   

        return response()->json($this->response);
    }

    //
}
