<?php

namespace App\Http\Controllers;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;

class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     *  
     * @return void
     */
    public $database;

    public function __construct()
    {
        $this->database = Firebase::database();
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
            //    $a[$i]->move($folder,$fileName);
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
