class DatabaseManager {
    public $centralCon;
    public $clientCon;

    public function __construct($centralCon, $clientCon) {
        $this->centralCon = $centralCon;
        $this->clientCon = $clientCon;
    }
}