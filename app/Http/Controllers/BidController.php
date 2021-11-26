<?php

namespace App\Http\Controllers;
use App\Models\Bid;
use Illuminate\Http\Request;
use App\Models\Product;
use Kreait\Laravel\Firebase\Facades\Firebase;

class BidController extends Controller
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

    public function placeBid(Request $request){
        /*
        payload: userid, userbid, productid
        */
        $data = $request->all();

        $insertData = [
            'user_id' => $data['userId'],
            'product_id' => $data['productId'],
            'amount' => $data['amount'],  
        ];

        $check = Bid::insert($insertData);

        $count = Bid::where(
            [
                ['user_id', '=', $insertData['user_id']],
                ['product_id', '=', $insertData['product_id']],
            ])->count();


        //update firebase
        $updates = [
            'products/'.$insertData['product_id'].'/total_bidder' => $count,
            'products/'.$insertData['product_id'].'/highest_bid' => $insertData['amount'],
        ];

        $this->database->getReference() // this is the root reference
        ->update($updates);

        //update database
        Product::where('id', $insertData['product_id'])->update(['total_bidder'=>$count, 'highest_bid'=>$insertData['amount']]);
        // Bid::insert($data);
        if($check){
            $this->response['status'] = 200;
            $this->response['data'] = $data;
            $this->response['message'] = "Berhasil Merequest Product";
        }else{
            $this->response['status'] = 505;
            $this->response['data'] = null;
            $this->response['message'] = "Terjadi Kesalahan!";
        }
   

        return response()->json($this->response);
        
        

        /*
        flow:
        - simpan data user bid di database
        - update data bid di firebase
        - update data product di sql database
        */
    }


    public function userBidData($userid){
        $bidData = Bid::where('user_id', $userid)->get()->toArray();


        if(!empty($bidData)){
            $this->response['status'] = 200;
            $this->response['message'] = "Berhasil Mengambil Data Bid";
            $this->response['data'] = $bidData;

        }else{
            $this->response['status'] = 505;
            $this->response['message'] = "Data Bid Tidak ditemukan.";
            $this->response['data'] = null;

        }
        
        return response()->json($this->response);
    }

    //
}
