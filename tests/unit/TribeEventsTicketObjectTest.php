<?php
use AspectMock\Test as Test;

class  TribeEventsTicketObjectTest extends \PHPUnit_Framework_TestCase {

	protected function setUp() {
	}

	protected function tearDown() {
		Test::clean();
	}

	/**
	 * @test
	 * it should leave property access unaltered
	 */
	public function it_should_leave_property_access_unaltered() {
		$sut       = new TribeEventsTicketObject();
		$sut->name = 'Foo';
		$this->assertEquals( 'Foo', $sut->name );
	}

	/**
	 * @test
	 * it should not affect the global stock by default
	 */
	public function it_should_not_affect_the_global_stock_by_default() {
		$sut = new TribeEventsTicketObject();
		$this->assertFalse( $sut->global_stock_id );
	}

	/**
	 * @test
	 * it should allow accessing the ticket stock if ticket does not affect global stock
	 */
	public function it_should_allow_accessing_the_ticket_stock_if_ticket_does_not_affect_global_stock() {
		$sut        = new TribeEventsTicketObject();
		$sut->stock = 23;
		$this->assertEquals( 23, $sut->stock );
	}

	/**
	 * @test
	 * it should get the event for the ticket when trying to set a global stock ticket
	 */
	public function it_should_get_the_event_for_the_ticket_when_trying_to_set_a_global_stock_ticket() {
		$sut                  = new TribeEventsTicketObject();
		$sut->ID              = 11;
		$sut->global_stock_id = 'stock1';
		$tet                  = Test::double( 'TribeEventsTickets', [ 'find_matching_event' => false ] );

		$this->setExpectedException( 'Exception' );
		$sut->stock = 1;

		$tet->verifyInvoked( 'find_matching_event', [ 11 ] );
	}

	public function values(){
		return array_map(function($val){
			return array($val);
		}, range(0, 1000, 50));
	}

	/**
	 * @test
	 * it should set the global stock when trying to set stock on global tickets
	 * @dataProvider values
	 */
	public function it_should_set_the_global_stock_when_trying_to_set_stock_on_global_tickets($value) {
		$sut                  = new TribeEventsTicketObject();
		$sut->ID              = 11;
		$sut->global_stock_id = 'stock1';
		$meta                 = [ 'stock1' => 30 ];
		$event                = $this->getMock( 'WP_Post');
		$event->{TribeEventsTicketObject::GLOBAL_STOCKS_META} = $meta;
		Test::double( 'TribeEventsTickets', [ 'find_matching_event' => $event ] );

		$sut->stock = $value;

		$this->assertEquals($value, $event->{TribeEventsTicketObject::GLOBAL_STOCKS_META}['stock1']);
	}

	/**
	 * @test
	 * it should get the global stock when trying to get stock of global ticket
	 * @dataProvider values
	 */
	public function it_should_get_the_global_stock_when_trying_to_get_stock_of_global_ticket($value) {
		$sut                  = new TribeEventsTicketObject();
		$sut->ID              = 11;
		$sut->global_stock_id = 'stock1';
		$meta                 = [ 'stock1' => $value ];
		$event                = $this->getMock( 'WP_Post');
		$event->{TribeEventsTicketObject::GLOBAL_STOCKS_META} = $meta;
		Test::double( 'TribeEventsTickets', [ 'find_matching_event' => $event ] );

		$this->assertEquals($value, $sut->stock);
	}

}